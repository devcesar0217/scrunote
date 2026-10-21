<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_or_mutate_notes(): void
    {
        $note = Note::factory()->create();
        $this->get(route('notes.index'))->assertRedirect(route('login'));
        $this->post(route('notes.store'))->assertRedirect(route('login'));
        $this->get(route('notes.show', $note))->assertRedirect(route('login'));
        $this->patch(route('notes.update', $note))->assertRedirect(route('login'));
        $this->patch(route('notes.pin', $note))->assertRedirect(route('login'));
        $this->delete(route('notes.destroy', $note))->assertRedirect(route('login'));
    }

    public function test_user_can_create_a_real_empty_note_and_open_its_editor(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('notes.store'));
        $note = Note::query()->sole();

        $response->assertRedirect(route('notes.show', $note));
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'user_id' => $user->id,
            'title' => null,
            'content_text' => '',
            'lock_version' => 0,
        ]);
        $this->actingAs($user)->get(route('notes.show', $note))->assertOk()
            ->assertSee('Untitled note')->assertSee('Rich-text formatting')
            ->assertSee('version: 0', false)
            ->assertSee('Save changes with the button below when JavaScript is unavailable.')
            ->assertSee('notes-noscript-save-button', false)
            ->assertDontSee('notes-save-button', false)
            ->assertSee('notes-rich-editor', false)
            ->assertDontSee('encrypted before they are saved', false)
            ->assertDontSee('SQLite');
    }

    public function test_note_update_sanitizes_rich_text_builds_search_text_and_syncs_current_users_tags(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->for($user)->create();
        $other = User::factory()->create();
        $foreignTag = Tag::factory()->for($other)->create(['module' => 'notes', 'name' => 'security']);
        $html = '<p onclick="alert(1)">Hello <b>world</b><script>alert(2)</script>'
            .'<a href="javascript:alert(3)">unsafe</a><a href="https://example.com" target="_blank">safe link</a></p>'
            .'<h2>Checklist</h2><ul><li>One</li><li>Two</li></ul>';

        $response = $this->actingAs($user)->patch(route('notes.update', $note), [
            'title' => '  Secure Linux lock file  ',
            'content_html' => $html,
            'tags' => ' #Security, Linux, Linux ',
            'version' => 0,
        ]);

        $response->assertRedirect(route('notes.show', $note))->assertSessionHas('status', 'Note saved.');
        $note->refresh();
        $safe = $note->content['html'];
        $this->assertSame('Secure Linux lock file', $note->title);
        $this->assertSame(1, $note->lock_version);
        $this->assertStringContainsString('<strong>world</strong>', $safe);
        $this->assertStringContainsString('href="https://example.com"', $safe);
        $this->assertStringContainsString('rel="noopener noreferrer"', $safe);
        $this->assertStringNotContainsString('<script', $safe);
        $this->assertStringNotContainsString('onclick', $safe);
        $this->assertStringNotContainsString('javascript:', $safe);
        $this->assertStringContainsString('Hello worldunsafe', $note->content_text);
        $this->assertStringContainsString('Checklist', $note->content_text);
        $this->assertStringContainsString('Two', $note->content_text);
        $this->assertSame(['linux', 'security'], $note->tags()->orderBy('name')->pluck('name')->all());
        $this->assertFalse($note->tags()->whereKey($foreignTag)->exists());
        $this->assertDatabaseHas('tags', ['user_id' => $user->id, 'module' => 'notes', 'name' => 'security']);
    }

    public function test_plain_text_no_javascript_fallback_is_saved_as_safe_note_content(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->for($user)->create();
        $this->actingAs($user)->patch(route('notes.update', $note), [
            'title' => 'Offline draft',
            'content_html' => "First line\nSecond <line>",
            'content_plain' => '1',
            'tags' => 'plain text',
            'version' => 0,
        ])->assertRedirect(route('notes.show', $note));

        $note->refresh();
        $this->assertSame("First line\n\nSecond <line>", $note->content_text);
        $this->assertStringContainsString('&lt;line&gt;', $note->content['html']);
        $this->assertStringContainsString('<br>', $note->content['html']);
    }

    public function test_search_matches_only_current_users_title_body_and_note_tags(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $title = Note::factory()->for($user)->create(['title' => 'RAC-970 title', 'content_text' => 'irrelevant']);
        $body = Note::factory()->for($user)->create(['title' => 'Body match', 'content_text' => 'Troubleshoot RAC-970 port']);
        $tagged = Note::factory()->for($user)->create(['title' => 'Tag match', 'content_text' => 'irrelevant']);
        $tag = Tag::factory()->for($user)->create(['module' => 'notes', 'name' => 'rac-970']);
        $tagged->tags()->attach($tag);
        $foreign = Note::factory()->for($other)->create(['title' => 'RAC-970 private', 'content_text' => 'RAC-970']);

        $response = $this->actingAs($user)->get(route('notes.index', ['q' => 'RAC-970']))->assertOk();
        foreach ([$title, $body, $tagged] as $note) {
            $response->assertSee($note->title);
        }
        $response->assertDontSee($foreign->title)->assertSee('3 notes found');

        $this->actingAs($user)->get(route('notes.index', ['q' => '%']))->assertOk()
            ->assertSee($title->title)->assertSee($body->title)->assertSee($tagged->title)
            ->assertDontSee($foreign->title);
    }

    public function test_owner_scoping_prevents_read_update_pin_and_delete_of_another_users_note(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $note = Note::factory()->for($owner)->create(['title' => 'Owner secret']);

        $this->actingAs($intruder)->get(route('notes.show', $note))->assertNotFound();
        $this->actingAs($intruder)->patch(route('notes.update', $note), [
            'title' => 'Stolen', 'content_html' => '<p>Stolen</p>', 'version' => 0,
        ])->assertNotFound();
        $this->actingAs($intruder)->patch(route('notes.pin', $note), ['version' => 0])->assertNotFound();
        $this->actingAs($intruder)->delete(route('notes.destroy', $note))->assertNotFound();
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'user_id' => $owner->id, 'title' => 'Owner secret']);
    }

    public function test_json_autosave_returns_a_new_version_and_rejects_stale_write_without_losing_the_saved_note(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->for($user)->create(['title' => 'Before']);
        $payload = ['title' => 'First saved value', 'content_html' => '<p>Saved body</p>', 'tags' => 'autosave', 'version' => 0];

        $this->actingAs($user)->patchJson(route('notes.update', $note), $payload)->assertOk()
            ->assertJsonPath('note.id', $note->id)->assertJsonPath('note.title', 'First saved value')
            ->assertJsonPath('note.version', 1)->assertJsonPath('note.preview', 'Saved body')
            ->assertJsonPath('note.tags', ['autosave']);
        $this->actingAs($user)->patchJson(route('notes.update', $note), [
            ...$payload,
            'title' => 'Stale overwrite',
        ])->assertStatus(409)->assertJsonPath('message', 'This note changed in another tab. Reload it before saving again.');

        $note->refresh();
        $this->assertSame('First saved value', $note->title);
        $this->assertSame(1, $note->lock_version);
        $this->assertSame('Saved body', $note->content_text);
    }

    public function test_pinning_is_owned_versioned_and_sorts_the_notes_list_first(): void
    {
        $user = User::factory()->create();
        $older = Note::factory()->for($user)->create(['title' => 'Older note', 'updated_at' => now()->subMinute()]);
        $newer = Note::factory()->for($user)->create(['title' => 'Newer note']);

        $this->actingAs($user)->patch(route('notes.pin', $older), ['version' => 0])
            ->assertRedirect(route('notes.show', $older))->assertSessionHas('status', 'Note pinned.');
        $older->refresh();
        $this->assertTrue($older->is_pinned);
        $this->assertSame(1, $older->lock_version);
        $this->actingAs($user)->get(route('notes.index'))->assertOk()
            ->assertSeeInOrder(['Older note', 'Newer note']);
        $this->actingAs($user)->patch(route('notes.pin', $older), ['version' => 0])->assertSessionHasErrors('version');
        $older->refresh();
        $this->assertTrue($older->is_pinned);
    }

    public function test_deleting_a_note_removes_its_pivot_rows_but_not_other_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $note = Note::factory()->for($user)->create();
        $tag = Tag::factory()->for($user)->create();
        $note->tags()->attach($tag);
        $otherNote = Note::factory()->for($other)->create();

        $this->actingAs($user)->delete(route('notes.destroy', $note))->assertRedirect(route('notes.index'));
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
        $this->assertDatabaseMissing('note_tags', ['note_id' => $note->id]);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
        $this->assertDatabaseHas('notes', ['id' => $otherNote->id]);
    }

    public function test_notes_rollback_drops_pivot_before_notes_and_can_migrate_again_on_mysql(): void
    {
        $this->assertTrue(Schema::hasTable('notes'));
        $this->assertTrue(Schema::hasTable('note_tags'));
        // Phase 5/6 migrations were added after Notes. Roll them, the Notes
        // lock-version migration, and the Notes table migration in one pass.
        Artisan::call('migrate:rollback', ['--step' => 5, '--no-interaction' => true]);
        $this->assertFalse(Schema::hasTable('notes'));
        $this->assertFalse(Schema::hasTable('note_tags'));
        $this->assertTrue(Schema::hasTable('tags'));
        Artisan::call('migrate', ['--no-interaction' => true]);
        $this->assertTrue(Schema::hasTable('notes'));
        $this->assertTrue(Schema::hasTable('note_tags'));
        $this->assertTrue(Schema::hasColumn('notes', 'lock_version'));
    }
}
