<?php

namespace Tests\Feature;

use App\Livewire\DocumentsSearch;
use App\Livewire\GlobalSearchPalette;
use App\Livewire\GlobalSearchResults;
use App\Livewire\NotesSearch;
use App\Livewire\VaultSearch;
use App\Models\Document;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LiveSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_and_markdown_searches_filter_live_with_owner_scoped_project_context(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['title' => 'Launch Project']);
        Note::factory()->for($owner)->create(['project_id' => $project->id, 'title' => 'Launch checklist', 'content_text' => 'Release marker']);
        Note::factory()->for($owner)->create(['title' => 'My Workspace note', 'content_text' => 'Personal marker']);
        Note::factory()->for($other)->create(['title' => 'Foreign release note', 'content_text' => 'Release marker']);
        Document::factory()->for($owner)->create(['project_id' => $project->id, 'title' => 'Launch runbook', 'content_text' => 'Release marker']);
        Document::factory()->for($owner)->create(['title' => 'My Workspace document', 'content_text' => 'Personal marker']);

        $this->actingAs($owner);
        Livewire::test(NotesSearch::class)
            ->set('search', 'release marker')
            ->assertSee('Launch checklist')->assertDontSee('My Workspace note')->assertDontSee('Foreign release note')
            ->set('search', '')
            ->set('projectFilter', 'mine')
            ->assertDispatched('workspace-project-filter-updated')
            ->assertSee('My Workspace note')->assertDontSee('Launch checklist');
        Livewire::test(DocumentsSearch::class)
            ->set('search', 'release marker')
            ->assertSee('Launch runbook')->assertDontSee('My Workspace document')
            ->set('search', '')
            ->set('projectFilter', $project->id)
            ->assertSee('Launch runbook')->assertDontSee('My Workspace document');
    }

    public function test_palette_dialog_shell_stays_outside_the_livewire_component_boundary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notes.index'))
            ->assertOk()
            ->assertSee('<dialog id="workspace-palette" class="workspace-palette" x-ref="palette"', false);
    }

    public function test_vault_live_search_uses_only_safe_metadata_and_global_live_search_keeps_secrets_excluded(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['title' => 'Migration Project']);
        $vault = Vault::factory()->for($user)->create();
        VaultEntry::factory()->for($vault)->create([
            'project_id' => $project->id,
            'title' => 'Migration database',
            'metadata' => ['host' => 'migration-db.internal.test'],
            'sensitive_payload' => 'never-search-this-secret',
        ]);
        VaultEntry::factory()->for($vault)->create([
            'title' => 'Private value only',
            'metadata' => [],
            'sensitive_payload' => 'secret-only-marker',
        ]);

        $this->actingAs($user);
        Livewire::test(VaultSearch::class)
            ->set('search', 'migration-db')
            ->assertSee('Migration database')->assertDontSee('Private value only')->assertDontSee('never-search-this-secret')
            ->set('search', '')
            ->set('projectFilter', 'mine')
            ->assertSee('Private value only')->assertDontSee('Migration database');
        Livewire::test(GlobalSearchResults::class)
            ->set('query', 'Migration Project')
            ->assertSee('Migration Project')->assertSee('Migration database')->assertDontSee('never-search-this-secret');
        Livewire::test(GlobalSearchPalette::class)
            ->set('query', 'Migration Project')
            ->assertSee('Migration Project')
            ->dispatch('reset-global-search')
            ->assertSet('query', '')
            ->set('query', 'secret-only-marker')
            ->assertDontSee('Private value only')->assertDontSee('secret-only-marker');
    }
}
