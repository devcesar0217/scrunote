<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_global_search_or_its_json_results(): void
    {
        $this->get(route('search.index'))->assertRedirect(route('login'));
        $this->getJson(route('search.index', ['q' => 'release']))->assertUnauthorized();
    }

    public function test_owner_can_search_notes_documents_tags_and_non_secret_vault_metadata(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $note = Note::factory()->for($owner)->create([
            'title' => 'Release checklist',
            'content_text' => 'Deploy the blue environment after checking migrations.',
        ]);
        $document = Document::factory()->for($owner)->create([
            'title' => 'Deployment guide',
            'content_text' => 'Release runbook and production deployment instructions.',
        ]);
        $vault = Vault::factory()->for($owner)->create();
        $vaultEntry = VaultEntry::factory()->for($vault)->create([
            'title' => 'Release database',
            'type' => 'database',
            'username' => 'release-admin',
            'metadata' => ['host' => 'release-db.internal.test', 'database' => 'scrunote'],
            'sensitive_payload' => 'encrypted-secret-marker-not-searchable',
        ]);
        $noteTag = Tag::factory()->for($owner)->create(['module' => 'notes', 'name' => 'release-tag']);
        $documentTag = Tag::factory()->for($owner)->create(['module' => 'markdown', 'name' => 'release-tag']);
        $vaultTag = Tag::factory()->for($owner)->create(['module' => 'vault', 'name' => 'release-tag']);
        $note->tags()->attach($noteTag);
        $document->tags()->attach($documentTag);
        $vaultEntry->tags()->attach($vaultTag);

        Note::factory()->for($other)->create(['title' => 'Release plan from another user', 'content_text' => 'release']);
        $otherVault = Vault::factory()->for($other)->create();
        VaultEntry::factory()->for($otherVault)->create(['title' => 'Other user release credential', 'metadata' => ['host' => 'release-db.internal.test']]);

        $response = $this->actingAs($owner)->getJson(route('search.index', ['q' => 'release']));
        $response->assertOk()
            ->assertJsonPath('minimum_query_length', 2)
            ->assertJsonCount(3, 'results')
            ->assertJsonPath('results.0.id', 'note:'.$note->id)
            ->assertJsonPath('results.1.id', 'document:'.$document->id)
            ->assertJsonPath('results.2.id', 'vault:'.$vaultEntry->id)
            ->assertJsonPath('results.0.url', route('notes.show', $note))
            ->assertJsonPath('results.1.url', route('markdown.show', $document))
            ->assertJsonPath('results.2.url', route('vault.show', $vaultEntry));

        $this->actingAs($owner)->getJson(route('search.index', ['q' => 'release-db.internal']))->assertOk()
            ->assertJsonCount(1, 'results')->assertJsonPath('results.0.id', 'vault:'.$vaultEntry->id);

        $this->actingAs($owner)->getJson(route('search.index', ['q' => 'release-tag']))->assertOk()
            ->assertJsonCount(3, 'results');
    }

    public function test_vault_secrets_are_never_selected_queried_or_returned_by_global_search(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        $entry = VaultEntry::factory()->for($vault)->create([
            'title' => 'Plain metadata title',
            'metadata' => ['host' => 'metadata-only.internal.test'],
            'sensitive_payload' => 'secret-value-only-search-marker',
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($user)->getJson(route('search.index', ['q' => 'secret-value-only-search-marker']));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()->assertJsonCount(0, 'results');
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/sensitive_payload/i', $query['query']);
        }

        $metadataResponse = $this->actingAs($user)->getJson(route('search.index', ['q' => 'metadata-only']));
        $metadataResponse->assertOk()->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', 'vault:'.$entry->id);
        $this->assertStringNotContainsString('secret-value-only-search-marker', json_encode($metadataResponse->json(), JSON_THROW_ON_ERROR));
    }

    public function test_locked_vault_metadata_is_searchable_without_unlocking_or_decrypting_entries(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->for($user)->create();
        $entry = VaultEntry::factory()->for($vault)->create([
            'title' => 'Locked metadata credential',
            'metadata' => ['environment' => 'staging'],
            'sensitive_payload' => 'not-decrypted',
        ]);

        $this->actingAs($user)->getJson(route('search.index', ['q' => 'staging']))->assertOk()
            ->assertJsonCount(1, 'results')->assertJsonPath('results.0.id', 'vault:'.$entry->id);
    }

    public function test_short_malformed_and_overlong_search_queries_are_handled_safely(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('search.index', ['q' => 'a']))->assertOk()
            ->assertJsonCount(0, 'results')->assertJsonPath('minimum_query_length', 2);
        $this->actingAs($user)->get(route('search.index', ['q' => 'a']))->assertOk()
            ->assertSee('Type at least 2 characters');
        $this->actingAs($user)->getJson(route('search.index', ['q' => str_repeat('a', 121)]))->assertStatus(422);
        $this->actingAs($user)->getJson(route('search.index', ['q' => ['unexpected']]))->assertStatus(422);
    }

    public function test_global_search_caps_each_module_and_marks_that_more_results_exist(): void
    {
        $user = User::factory()->create();
        Note::factory()->count(9)->for($user)->create([
            'title' => 'Capped search result',
            'content_text' => 'cap marker',
        ]);

        $this->actingAs($user)->getJson(route('search.index', ['q' => 'cap marker']))->assertOk()
            ->assertJsonCount(8, 'results')->assertJsonPath('has_more', true);
    }
}
