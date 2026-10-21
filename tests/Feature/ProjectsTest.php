<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_or_mutate_projects(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.index'))->assertRedirect(route('login'));
        $this->post(route('projects.store'))->assertRedirect(route('login'));
        $this->get(route('projects.show', $project))->assertRedirect(route('login'));
        $this->patch(route('projects.update', $project))->assertRedirect(route('login'));
        $this->delete(route('projects.destroy', $project))->assertRedirect(route('login'));
    }

    public function test_owner_can_create_and_view_a_project_with_safe_content_counts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['title' => 'Release preparation', 'description' => 'Everything needed for the deploy.']);
        Note::factory()->for($user)->create(['project_id' => $project->id, 'title' => 'Release Notes']);
        Document::factory()->for($user)->create(['project_id' => $project->id, 'title' => 'Release Runbook']);
        $vault = Vault::factory()->for($user)->create();
        VaultEntry::factory()->for($vault)->create([
            'project_id' => $project->id,
            'title' => 'Release credential',
            'sensitive_payload' => 'must-not-appear-in-project-view',
        ]);

        $this->actingAs($user)->get(route('projects.index'))->assertOk()
            ->assertSee('Projects')->assertSee('Release preparation')->assertSee('My Workspace')
            ->assertSee('1 Notes')->assertSee('1 Docs')->assertSee('1 Vault');
        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('Release Notes')->assertSee('Release Runbook')->assertSee('Release credential')
            ->assertDontSee('must-not-appear-in-project-view');
    }

    public function test_project_assignment_is_owner_scoped_and_notes_can_be_filtered_to_a_project_or_my_workspace(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['title' => 'Owner Project']);
        $otherProject = Project::factory()->for(User::factory()->create())->create(['title' => 'Other Project']);
        $note = Note::factory()->for($owner)->create(['title' => 'Unassigned note', 'content' => ['html' => '<p>Body</p>'], 'content_text' => 'Body']);
        $assigned = Note::factory()->for($owner)->create(['project_id' => $project->id, 'title' => 'Assigned note']);

        $this->actingAs($owner)->patchJson(route('notes.update', $note), [
            'title' => 'Moved into Project',
            'content_html' => '<p>Body</p>',
            'tags' => '',
            'project_id' => $project->id,
            'version' => 0,
        ])->assertOk()->assertJsonPath('note.project.id', $project->id)
            ->assertJsonPath('note.project.title', 'Owner Project');
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'project_id' => $project->id]);

        $this->actingAs($owner)->patch(route('notes.update', $note), [
            'title' => 'Forbidden move', 'content_html' => '<p>Body</p>', 'tags' => '', 'project_id' => $otherProject->id, 'version' => 1,
        ])->assertSessionHasErrors('project_id');

        Note::factory()->for($owner)->create(['title' => 'Still in My Workspace']);
        $this->actingAs($owner)->get(route('notes.index', ['project' => $project->id]))->assertOk()
            ->assertSee('Moved into Project')->assertSee($assigned->title)->assertDontSee('Still in My Workspace');
        $this->actingAs($owner)->get(route('notes.index', ['project' => 'mine']))->assertOk()
            ->assertSee('Still in My Workspace')->assertDontSee('Moved into Project');
    }

    public function test_deleting_a_project_unassigns_all_linked_content_without_deleting_it(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $note = Note::factory()->for($user)->create(['project_id' => $project->id]);
        $document = Document::factory()->for($user)->create(['project_id' => $project->id]);
        $vault = Vault::factory()->for($user)->create();
        $entry = VaultEntry::factory()->for($vault)->create(['project_id' => $project->id]);

        $this->actingAs($user)->delete(route('projects.destroy', $project), ['confirm_project_deletion' => '1'])
            ->assertRedirect(route('projects.index'))->assertSessionHas('project_status');

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'project_id' => null]);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'project_id' => null]);
        $this->assertDatabaseHas('vault_entries', ['id' => $entry->id, 'project_id' => null]);
    }

    public function test_other_users_cannot_view_update_or_delete_a_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['title' => 'Private project']);

        $this->actingAs($intruder)->get(route('projects.show', $project))->assertNotFound();
        $this->actingAs($intruder)->patch(route('projects.update', $project), ['title' => 'Stolen'])->assertNotFound();
        $this->actingAs($intruder)->delete(route('projects.destroy', $project), ['confirm_project_deletion' => '1'])->assertNotFound();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'user_id' => $owner->id, 'title' => 'Private project']);
    }
}
