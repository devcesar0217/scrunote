<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vault;
use App\Models\VaultEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VaultTest extends TestCase
{
    use RefreshDatabase;

    private const MASTER_PASSWORD = 'correct-horse-battery-staple-2026';

    public function test_guests_cannot_access_or_mutate_the_vault(): void
    {
        $entry = VaultEntry::factory()->create();

        $this->get(route('vault.index'))->assertRedirect(route('login'));
        $this->post(route('vault.setup'))->assertRedirect(route('login'));
        $this->post(route('vault.unlock'))->assertRedirect(route('login'));
        $this->post(route('vault.lock'))->assertRedirect(route('login'));
        $this->post(route('vault.heartbeat'))->assertRedirect(route('login'));
        $this->get(route('vault.show', $entry))->assertRedirect(route('login'));
        $this->post(route('vault.entries.store'))->assertRedirect(route('login'));
        $this->patch(route('vault.entries.update', $entry))->assertRedirect(route('login'));
        $this->patch(route('vault.entries.pin', $entry))->assertRedirect(route('login'));
        $this->post(route('vault.entries.secret', $entry))->assertRedirect(route('login'));
        $this->delete(route('vault.entries.destroy', $entry))->assertRedirect(route('login'));
    }

    public function test_user_can_create_a_separate_master_password_vault_without_persisting_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('vault.setup'), [
            'master_password' => self::MASTER_PASSWORD,
            'master_password_confirmation' => self::MASTER_PASSWORD,
        ])->assertRedirect(route('vault.index'))->assertSessionHas('vault_status', 'Your Secure Vault is configured and unlocked.');

        $vault = Vault::query()->sole();
        $this->assertSame($user->id, $vault->user_id);
        $this->assertNotSame(self::MASTER_PASSWORD, $vault->getRawOriginal('wrapped_key'));
        $this->assertStringNotContainsString(self::MASTER_PASSWORD, $vault->getRawOriginal('wrapped_key'));
        $this->assertSame(SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, $vault->kdf_opslimit);
        $this->assertSame(SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, $vault->kdf_memlimit);
        $this->assertMasterPasswordWasNotFlashed();
    }

    public function test_setup_validation_does_not_flash_master_password_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('vault.index'))->post(route('vault.setup'), [
            'master_password' => 'too-short',
            'master_password_confirmation' => 'different',
        ])->assertRedirect(route('vault.index'))->assertSessionHasErrors('master_password');

        $this->assertDatabaseCount('vaults', 0);
        $this->assertMasterPasswordWasNotFlashed();
    }

    public function test_unlocked_user_can_store_and_explicitly_reveal_an_encrypted_entry_without_secret_markup(): void
    {
        $user = User::factory()->create();
        $this->setupVault($user);

        $secret = 'Only-this-test-knows-the-secret';
        $notes = 'Rotate it after production maintenance.';
        $this->actingAs($user)->post(route('vault.entries.store'), [
            'title' => 'Production database',
            'type' => 'database',
            'url' => 'https://db.example.test',
            'username' => 'database-admin',
            'secret_kind' => 'password',
            'secret' => $secret,
            'notes' => $notes,
            'metadata' => ['host' => 'db.internal.test', 'port' => '3306', 'database' => 'app', 'engine' => 'mariadb'],
            'tags' => 'production, database',
        ])->assertRedirect();

        $entry = VaultEntry::query()->with('tags')->sole();
        $this->assertSame(3306, $entry->metadata['port']);
        $rawPayload = (string) DB::table('vault_entries')->where('id', $entry->id)->value('sensitive_payload');
        $this->assertStringNotContainsString($secret, $rawPayload);
        $this->assertStringNotContainsString($notes, $rawPayload);
        $this->assertSame(['database', 'production'], $entry->tags->sortBy('name')->pluck('name')->values()->all());

        $this->actingAs($user)->get(route('vault.show', $entry))->assertOk()
            ->assertSee('Production database')
            ->assertDontSee($secret)
            ->assertDontSee($notes)
            ->assertSee('••••••••••••••••');

        $reveal = $this->actingAs($user)->postJson(route('vault.entries.secret', $entry));
        $reveal->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('secret', $secret)
            ->assertJsonPath('notes', $notes);
        $this->assertStringContainsString('no-store', (string) $reveal->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $reveal->headers->get('Cache-Control'));
    }

    public function test_secure_note_type_does_not_retain_irrelevant_plaintext_login_fields(): void
    {
        $user = User::factory()->create();
        $this->setupVault($user);

        $this->actingAs($user)->post(route('vault.entries.store'), [
            'title' => 'Private recovery instructions',
            'type' => 'secure_note',
            'url' => 'https://should-not-be-retained.example.test',
            'username' => 'should-not-be-retained',
            'secret_kind' => 'secure_note',
            'secret' => 'the encrypted secure note',
            'notes' => 'also encrypted',
            'metadata' => ['host' => 'should-not-be-retained.example.test'],
            'tags' => 'recovery',
        ])->assertRedirect();

        $entry = VaultEntry::query()->sole();
        $this->assertSame('secure_note', $entry->type);
        $this->assertSame('secure_note', $entry->secret_kind);
        $this->assertNull($entry->url);
        $this->assertNull($entry->username);
        $this->assertSame([], $entry->metadata);
    }

    public function test_locked_vault_rejects_secret_and_mutation_routes_until_correctly_unlocked(): void
    {
        $user = User::factory()->create();
        $this->setupVault($user);
        $entry = $this->storeEntry($user, 'Locked entry', 'secret-before-lock');

        $this->actingAs($user)->post(route('vault.lock'))->assertRedirect(route('vault.index'));
        $this->actingAs($user)->postJson(route('vault.entries.secret', $entry))->assertStatus(423)
            ->assertJsonPath('message', 'Unlock your Vault before accessing credential secrets.');
        $this->actingAs($user)->post(route('vault.entries.store'), $this->entryPayload('Cannot save while locked', 'secret'))
            ->assertRedirect(route('vault.index'));
        $this->assertDatabaseCount('vault_entries', 1);

        $this->actingAs($user)->from(route('vault.index'))->post(route('vault.unlock'), ['master_password' => 'incorrect master password'])
            ->assertRedirect(route('vault.index'))->assertSessionHasErrors('master_password');
        $this->assertMasterPasswordWasNotFlashed();

        $this->actingAs($user)->post(route('vault.unlock'), ['master_password' => self::MASTER_PASSWORD])
            ->assertRedirect(route('vault.index'));
        $this->actingAs($user)->postJson(route('vault.entries.secret', $entry))->assertOk()
            ->assertJsonPath('secret', 'secret-before-lock');
    }

    public function test_another_unlocked_user_cannot_discover_or_access_an_entry(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->setupVault($owner);
        $entry = $this->storeEntry($owner, 'Owner-only credential', 'owner-secret');
        $this->setupVault($intruder);

        $this->actingAs($intruder)->get(route('vault.show', $entry))->assertNotFound();
        $this->actingAs($intruder)->postJson(route('vault.entries.secret', $entry))->assertNotFound();
        $this->actingAs($intruder)->patch(route('vault.entries.update', $entry), [
            ...$this->entryPayload('Stolen', 'attempted-secret'),
            'version' => 0,
        ])->assertNotFound();
        $this->actingAs($intruder)->patch(route('vault.entries.pin', $entry), ['version' => 0])->assertNotFound();
        $this->actingAs($intruder)->delete(route('vault.entries.destroy', $entry))->assertNotFound();
        $this->assertDatabaseHas('vault_entries', ['id' => $entry->id, 'title' => 'Owner-only credential']);
    }

    public function test_stale_entry_update_is_rejected_without_replacing_the_existing_encrypted_secret(): void
    {
        $user = User::factory()->create();
        $this->setupVault($user);
        $entry = $this->storeEntry($user, 'Before update', 'original-secret');
        $payload = [
            ...$this->entryPayload('First saved title', ''),
            'secret' => '',
            'version' => 0,
        ];

        $this->actingAs($user)->patch(route('vault.entries.update', $entry), $payload)
            ->assertRedirect(route('vault.show', $entry));
        $entry->refresh();
        $this->assertSame('First saved title', $entry->title);
        $this->assertSame(1, $entry->lock_version);

        $this->actingAs($user)->patch(route('vault.entries.update', $entry), [...$payload, 'title' => 'Stale overwrite'])
            ->assertSessionHasErrors('version');
        $entry->refresh();
        $this->assertSame('First saved title', $entry->title);
        $this->assertSame(1, $entry->lock_version);
        $this->actingAs($user)->postJson(route('vault.entries.secret', $entry))->assertOk()
            ->assertJsonPath('secret', 'original-secret');
    }

    public function test_vault_server_expiry_locks_after_five_minutes_without_activity(): void
    {
        $user = User::factory()->create();
        $this->setupVault($user);
        $entry = $this->storeEntry($user, 'Timed credential', 'timed-secret');

        $this->travel(6)->minutes();
        $this->actingAs($user)->postJson(route('vault.entries.secret', $entry))->assertStatus(423);
        $this->travelBack();
    }

    public function test_vault_search_matches_only_metadata_and_never_secret_values(): void
    {
        $user = User::factory()->create();
        $this->setupVault($user);
        $metadataEntry = $this->storeEntry($user, 'Deployment SSH', 'private-value-needle', [
            'type' => 'server',
            'metadata' => ['host' => 'deploy.searchable.internal', 'port' => 22, 'protocol' => 'ssh'],
            'tags' => 'production, ssh',
        ]);
        $secretOnlyEntry = $this->storeEntry($user, 'Private item', 'search-only-secret-needle');

        $this->actingAs($user)->get(route('vault.index', ['q' => 'production']))->assertOk()
            ->assertSee($metadataEntry->title)->assertDontSee($secretOnlyEntry->title);
        $this->actingAs($user)->get(route('vault.index', ['q' => 'searchable.internal']))->assertOk()
            ->assertSee($metadataEntry->title)->assertDontSee($secretOnlyEntry->title);
        $this->actingAs($user)->get(route('vault.index', ['q' => 'search-only-secret-needle']))->assertOk()
            ->assertDontSee($metadataEntry->title)->assertDontSee($secretOnlyEntry->title);
    }

    public function test_vault_entry_migration_rolls_back_its_pivot_before_entries_and_can_migrate_again(): void
    {
        $this->assertTrue(Schema::hasTable('vaults'));
        $this->assertTrue(Schema::hasTable('vault_entries'));
        $this->assertTrue(Schema::hasTable('vault_entry_tags'));

        // Projects and Documents have later timestamps. Roll those plus the
        // Vault-entry migration so its pivot-first down path is exercised while
        // the parent Vault table remains available.
        Artisan::call('migrate:rollback', ['--step' => 3, '--no-interaction' => true]);
        $this->assertTrue(Schema::hasTable('vaults'));
        $this->assertFalse(Schema::hasTable('vault_entries'));
        $this->assertFalse(Schema::hasTable('vault_entry_tags'));

        Artisan::call('migrate', ['--no-interaction' => true]);
        $this->assertTrue(Schema::hasTable('vault_entries'));
        $this->assertTrue(Schema::hasTable('vault_entry_tags'));
    }

    private function setupVault(User $user): Vault
    {
        $this->actingAs($user)->post(route('vault.setup'), [
            'master_password' => self::MASTER_PASSWORD,
            'master_password_confirmation' => self::MASTER_PASSWORD,
        ])->assertRedirect(route('vault.index'));

        return Vault::query()->whereBelongsTo($user)->sole();
    }

    /** @param array<string, mixed> $overrides */
    private function storeEntry(User $user, string $title, string $secret, array $overrides = []): VaultEntry
    {
        $this->actingAs($user)->post(route('vault.entries.store'), [
            ...$this->entryPayload($title, $secret),
            ...$overrides,
        ])->assertRedirect();

        return VaultEntry::query()->latest('id')->firstOrFail();
    }

    private function assertMasterPasswordWasNotFlashed(): void
    {
        $oldInput = $this->app['session']->getOldInput();

        $this->assertArrayNotHasKey('master_password', $oldInput);
        $this->assertArrayNotHasKey('master_password_confirmation', $oldInput);
    }

    /** @return array<string, mixed> */
    private function entryPayload(string $title, string $secret): array
    {
        return [
            'title' => $title,
            'type' => 'login',
            'url' => 'https://example.test/login',
            'username' => 'developer',
            'secret_kind' => 'password',
            'secret' => $secret,
            'notes' => '',
            'metadata' => [],
            'tags' => '',
        ];
    }
}
