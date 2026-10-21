<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Note;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultEntry;
use App\Support\VaultCryptography;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_settings_or_workspace_backup_actions(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));
        $this->post(route('settings.backup.create'))->assertRedirect(route('login'));
        $this->post(route('settings.backup.restore'))->assertRedirect(route('login'));
    }

    public function test_settings_page_has_workable_theme_and_backup_controls_without_claiming_server_storage(): void
    {
        $this->actingAs(User::factory()->create())->get(route('settings.index'))->assertOk()
            ->assertViewIs('pages.settings.index')
            ->assertSee('Appearance')->assertSee('Dark')->assertSee('Light')
            ->assertSee('Encrypted workspace backup')->assertSee('Create backup')->assertSee('Restore backup')
            ->assertSee(route('settings.backup.create'), false)->assertSee(route('settings.backup.restore'), false)
            ->assertSee('never retained on this server')
            ->assertDontSee('OS credential store')->assertDontSee('Choose a folder on this server');
    }

    public function test_encrypted_backup_round_trip_replaces_workspace_and_preserves_vault_ciphertext(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['title' => 'Retained Project']);
        $note = Note::factory()->for($user)->create([
            'project_id' => $project->id,
            'title' => 'Retained note',
            'content' => ['html' => '<p>Retained <strong>workspace</strong> note.</p>'],
            'content_text' => 'Retained workspace note.',
        ]);
        $document = Document::factory()->for($user)->create([
            'project_id' => $project->id,
            'title' => 'Retained document',
            'markdown' => '# Retained document',
            'content_text' => 'Retained document',
        ]);
        $noteTag = Tag::factory()->for($user)->create(['module' => 'notes', 'name' => 'retained']);
        $documentTag = Tag::factory()->for($user)->create(['module' => 'markdown', 'name' => 'retained-doc']);
        $note->tags()->attach($noteTag);
        $document->tags()->attach($documentTag);
        [$vault, $entry, $masterPassword] = $this->vaultEntry($user, 'Retained encrypted credential', 'vault-secret-that-must-never-leak');
        $entry->update(['project_id' => $project->id]);
        $vaultTag = Tag::factory()->for($user)->create(['module' => 'vault', 'name' => 'retained-vault']);
        $entry->tags()->attach($vaultTag);
        $originalVaultId = $vault->id;

        // Browser form values remain strings after Laravel's integer validation.
        // Ensure a valid Server metadata port survives a backup/restore cycle.
        $cryptography = app(VaultCryptography::class);
        $key = $cryptography->unlockVault($vault, $masterPassword);
        try {
            $serverEntry = new VaultEntry([
                'type' => 'server',
                'title' => 'Port stored from form data',
                'url' => null,
                'username' => 'deploy',
                'secret_kind' => 'password',
                'metadata' => ['host' => 'server.example.test', 'port' => '443', 'protocol' => 'ssh'],
            ]);
            $serverEntry->sensitive_payload = $cryptography->encryptEntryPayload($vault, $key, ['secret' => 'server-secret', 'notes' => '']);
            $vault->entries()->save($serverEntry);
        } finally {
            sodium_memzero($key);
        }

        $backupResponse = $this->actingAs($user)->post(route('settings.backup.create'), [
            'backup_password' => 'a unique backup password',
            'backup_password_confirmation' => 'a unique backup password',
            'backup_acknowledgement' => '1',
        ]);
        $backupResponse->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.scrunote.workspace-backup+json; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $backupResponse->headers->get('Cache-Control'));
        $backup = $backupResponse->getContent();
        $this->assertStringContainsString('scrunote.workspace-backup', $backup);
        $this->assertStringNotContainsString('vault-secret-that-must-never-leak', $backup);
        $this->assertStringNotContainsString('sensitive_payload', $backup);

        $user->notes()->update(['title' => 'Changed after backup']);
        Document::factory()->for($user)->create(['title' => 'Document that must be removed']);
        $user->vault->entries()->firstOrFail()->delete();

        $this->actingAs($user)->post(route('settings.backup.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('workspace.scrunote', $backup),
            'backup_password' => 'a unique backup password',
            'replace_workspace' => '1',
        ])->assertRedirect(route('settings.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('settings_status');

        $this->assertDatabaseHas('notes', ['user_id' => $user->id, 'title' => 'Retained note']);
        $this->assertDatabaseMissing('notes', ['user_id' => $user->id, 'title' => 'Changed after backup']);
        $this->assertDatabaseHas('documents', ['user_id' => $user->id, 'title' => 'Retained document']);
        $this->assertDatabaseMissing('documents', ['user_id' => $user->id, 'title' => 'Document that must be removed']);
        $this->assertDatabaseHas('tags', ['user_id' => $user->id, 'module' => 'vault', 'name' => 'retained-vault']);
        $restoredProject = Project::query()->where('user_id', $user->id)->where('title', 'Retained Project')->sole();
        $this->assertDatabaseHas('notes', ['user_id' => $user->id, 'title' => 'Retained note', 'project_id' => $restoredProject->id]);
        $this->assertDatabaseHas('documents', ['user_id' => $user->id, 'title' => 'Retained document', 'project_id' => $restoredProject->id]);

        $restoredVault = Vault::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame($originalVaultId, $restoredVault->id);
        $restoredEntry = $restoredVault->entries()->where('title', 'Retained encrypted credential')->firstOrFail();
        $this->assertSame($restoredProject->id, $restoredEntry->project_id);
        $this->assertSame('vault-secret-that-must-never-leak', $this->decrypt($restoredVault, $restoredEntry, $masterPassword));
        $restoredServer = $restoredVault->entries()->where('title', 'Port stored from form data')->firstOrFail();
        $this->assertSame(443, $restoredServer->metadata['port']);
        $this->assertSame('server-secret', $this->decrypt($restoredVault, $restoredServer, $masterPassword));

        // A restore clears any cached Vault key; a restored secret requires the
        // original master password and a new unlock.
        $this->actingAs($user)->postJson(route('vault.entries.secret', $restoredEntry))->assertStatus(423);
    }

    public function test_phase_eight_schema_one_backups_restore_with_content_in_my_workspace(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->for($user)->create(['title' => 'Legacy unassigned note']);
        $password = 'legacy backup password';
        $legacyBackup = $this->asPhaseEightSchemaOneBackup($this->backupFor($user, $password), $password);

        $project = Project::factory()->for($user)->create(['title' => 'Project added later']);
        $note->update(['project_id' => $project->id]);

        $this->actingAs($user)->post(route('settings.backup.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('phase-eight.scrunote', $legacyBackup),
            'backup_password' => $password,
            'replace_workspace' => '1',
        ])->assertRedirect(route('settings.index'))->assertSessionHasNoErrors();

        $this->assertSame(0, $user->projects()->count());
        $this->assertDatabaseHas('notes', ['user_id' => $user->id, 'title' => 'Legacy unassigned note', 'project_id' => null]);
    }

    public function test_another_users_backup_cannot_replace_the_authenticated_users_workspace(): void
    {
        $source = User::factory()->create();
        Note::factory()->for($source)->create(['title' => 'Source-only backup note']);
        $backup = $this->backupFor($source, 'source backup password');

        $target = User::factory()->create();
        Note::factory()->for($target)->create(['title' => 'Target note must remain']);
        $this->actingAs($target)->post(route('settings.backup.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('source.scrunote', $backup),
            'backup_password' => 'source backup password',
            'replace_workspace' => '1',
        ])->assertRedirect()
            ->assertSessionHasErrors('restore_backup');

        $this->assertDatabaseHas('notes', ['user_id' => $target->id, 'title' => 'Target note must remain']);
        $this->assertDatabaseMissing('notes', ['user_id' => $target->id, 'title' => 'Source-only backup note']);
    }

    public function test_wrong_password_or_tampered_backup_never_replaces_workspace_and_passwords_are_not_flashed(): void
    {
        $user = User::factory()->create();
        Note::factory()->for($user)->create(['title' => 'Keep this note']);
        $backup = $this->backupFor($user, 'correct backup password');

        $response = $this->actingAs($user)->post(route('settings.backup.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('workspace.scrunote', $backup),
            'backup_password' => 'wrong backup password',
            'replace_workspace' => '1',
        ]);
        $response->assertRedirect()->assertSessionHasErrors('restore_backup')
            ->assertSessionMissing('_old_input.backup_password');
        $this->assertDatabaseHas('notes', ['user_id' => $user->id, 'title' => 'Keep this note']);

        $envelope = json_decode($backup, true, flags: JSON_THROW_ON_ERROR);
        $envelope['ciphertext'] = base64_encode(random_bytes(96));
        $tampered = json_encode($envelope, JSON_THROW_ON_ERROR);
        $this->actingAs($user)->post(route('settings.backup.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('workspace.scrunote', $tampered),
            'backup_password' => 'correct backup password',
            'replace_workspace' => '1',
        ])->assertRedirect()->assertSessionHasErrors('restore_backup');
        $this->assertDatabaseHas('notes', ['user_id' => $user->id, 'title' => 'Keep this note']);
    }

    public function test_backup_actions_are_rate_limited_per_authenticated_user_and_ip(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear('workspace-backup');

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->actingAs($user)->post(route('settings.backup.restore'), [])->assertSessionHasErrors(['backup_file', 'backup_password', 'replace_workspace']);
        }
        $this->actingAs($user)->post(route('settings.backup.restore'), [])->assertStatus(429);
    }

    /** @return array{0: Vault, 1: VaultEntry, 2: string} */
    private function vaultEntry(User $user, string $title, string $secret): array
    {
        $masterPassword = 'original vault master password';
        $cryptography = app(VaultCryptography::class);
        $vault = $user->vault()->create($cryptography->createVaultMaterial($masterPassword));
        $key = $cryptography->unlockVault($vault, $masterPassword);
        try {
            $entry = new VaultEntry([
                'type' => 'login',
                'title' => $title,
                'url' => 'https://example.test',
                'username' => 'owner@example.test',
                'secret_kind' => 'password',
                'metadata' => [],
            ]);
            $entry->sensitive_payload = $cryptography->encryptEntryPayload($vault, $key, ['secret' => $secret, 'notes' => '']);
            $vault->entries()->save($entry);
        } finally {
            sodium_memzero($key);
        }

        return [$vault, $entry, $masterPassword];
    }

    private function decrypt(Vault $vault, VaultEntry $entry, string $masterPassword): string
    {
        $cryptography = app(VaultCryptography::class);
        $key = $cryptography->unlockVault($vault, $masterPassword);
        try {
            return $cryptography->decryptEntryPayload($vault, $key, $entry->sensitive_payload)['secret'];
        } finally {
            sodium_memzero($key);
        }
    }

    private function asPhaseEightSchemaOneBackup(string $backup, string $password): string
    {
        $envelope = json_decode($backup, true, flags: JSON_THROW_ON_ERROR);
        $salt = base64_decode($envelope['kdf']['salt'], true);
        $nonce = base64_decode($envelope['nonce'], true);
        $ciphertext = base64_decode($envelope['ciphertext'], true);
        $this->assertNotFalse($salt);
        $this->assertNotFalse($nonce);
        $this->assertNotFalse($ciphertext);
        $key = sodium_crypto_pwhash(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            $password,
            $salt,
            $envelope['kdf']['opslimit'],
            $envelope['kdf']['memlimit'],
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, 'scrunote-workspace-backup:v1', $nonce, $key);
            $this->assertNotFalse($plaintext);
            $snapshot = json_decode($plaintext, true, flags: JSON_THROW_ON_ERROR);
            $snapshot['schema'] = 1;
            unset($snapshot['projects']);
            foreach (['notes', 'documents'] as $collection) {
                foreach ($snapshot[$collection] as &$record) {
                    unset($record['project_id']);
                }
                unset($record);
            }
            if ($snapshot['vault'] !== null) {
                foreach ($snapshot['vault']['entries'] as &$entry) {
                    unset($entry['project_id']);
                }
                unset($entry);
            }
            $legacyPlaintext = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $envelope['ciphertext'] = base64_encode(sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $legacyPlaintext,
                'scrunote-workspace-backup:v1',
                $nonce,
                $key,
            ));

            return json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } finally {
            sodium_memzero($key);
        }
    }

    private function backupFor(User $user, string $password): string
    {
        return $this->actingAs($user)->post(route('settings.backup.create'), [
            'backup_password' => $password,
            'backup_password_confirmation' => $password,
            'backup_acknowledgement' => '1',
        ])->getContent();
    }
}
