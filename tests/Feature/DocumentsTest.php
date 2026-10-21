<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_or_mutate_documents(): void
    {
        $document = Document::factory()->create();
        $this->get(route('markdown.index'))->assertRedirect(route('login'));
        $this->post(route('markdown.store'))->assertRedirect(route('login'));
        $this->get(route('markdown.show', $document))->assertRedirect(route('login'));
        $this->patch(route('markdown.update', $document))->assertRedirect(route('login'));
        $this->patch(route('markdown.pin', $document))->assertRedirect(route('login'));
        $this->get(route('markdown.export', $document))->assertRedirect(route('login'));
        $this->delete(route('markdown.destroy', $document))->assertRedirect(route('login'));
    }

    public function test_user_can_create_a_real_empty_document_and_open_its_codemirror_editor(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('markdown.store'));
        $document = Document::query()->sole();

        $response->assertRedirect(route('markdown.show', $document));
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'user_id' => $user->id,
            'title' => null,
            'markdown' => '',
            'content_text' => '',
            'lock_version' => 0,
        ]);
        $this->actingAs($user)->get(route('markdown.show', $document))->assertOk()
            ->assertSee('Untitled document')->assertSee('CodeMirror 6')
            ->assertSee('version: 0', false)
            ->assertSee('Save changes with the button below when JavaScript is unavailable.')
            ->assertSee('markdown-plain-fallback', false)
            ->assertSee('markdown-codemirror', false)
            ->assertDontSee('type="file"', false);
    }

    public function test_document_update_keeps_markdown_source_builds_search_text_and_syncs_current_users_tags(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create();
        $other = User::factory()->create();
        $foreignTag = Tag::factory()->for($other)->create(['module' => 'markdown', 'name' => 'security']);
        $markdown = "# Secure Linux lock file\n\n- [x] Check permissions\n- [ ] Rotate key\n\n[Safe link](https://example.com)\n\n![Diagram](https://example.com/diagram.png)\n\n<script>alert(1)</script>";

        $response = $this->actingAs($user)->patch(route('markdown.update', $document), [
            'title' => '  Secure Linux lock file  ',
            'markdown' => $markdown,
            'tags' => ' #Security, Linux, Linux ',
            'version' => 0,
        ]);

        $response->assertRedirect(route('markdown.show', $document))->assertSessionHas('status', 'Document saved.');
        $document->refresh();
        $this->assertSame('Secure Linux lock file', $document->title);
        $this->assertSame($markdown, $document->markdown);
        $this->assertSame(1, $document->lock_version);
        $this->assertStringContainsString('Check permissions', $document->content_text);
        $this->assertStringContainsString('Safe link', $document->content_text);
        $this->assertStringContainsString('Diagram', $document->content_text);
        $this->assertFalse($document->tags()->whereKey($foreignTag)->exists());
        $this->assertSame(['linux', 'security'], $document->tags()->orderBy('name')->pluck('name')->all());
        $this->assertDatabaseHas('tags', ['user_id' => $user->id, 'module' => 'markdown', 'name' => 'security']);
    }

    public function test_search_matches_only_current_users_title_body_and_document_tags(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $title = Document::factory()->for($user)->create(['title' => 'RAC-970 title', 'content_text' => 'irrelevant']);
        $body = Document::factory()->for($user)->create(['title' => 'Body match', 'content_text' => 'Troubleshoot RAC-970 port']);
        $tagged = Document::factory()->for($user)->create(['title' => 'Tag match', 'content_text' => 'irrelevant']);
        $tag = Tag::factory()->for($user)->create(['module' => 'markdown', 'name' => 'rac-970']);
        $tagged->tags()->attach($tag);
        $foreign = Document::factory()->for($other)->create(['title' => 'RAC-970 private', 'content_text' => 'RAC-970']);

        $response = $this->actingAs($user)->get(route('markdown.index', ['q' => 'RAC-970']))->assertOk();
        foreach ([$title, $body, $tagged] as $document) {
            $response->assertSee($document->title);
        }
        $response->assertDontSee($foreign->title)->assertSee('3 documents found');
    }

    public function test_owner_scoping_prevents_read_update_pin_export_and_delete_of_another_users_document(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $document = Document::factory()->for($owner)->create(['title' => 'Owner secret']);

        $this->actingAs($intruder)->get(route('markdown.show', $document))->assertNotFound();
        $this->actingAs($intruder)->patch(route('markdown.update', $document), [
            'title' => 'Stolen', 'markdown' => '# Stolen', 'version' => 0,
        ])->assertNotFound();
        $this->actingAs($intruder)->patch(route('markdown.pin', $document), ['version' => 0])->assertNotFound();
        $this->actingAs($intruder)->get(route('markdown.export', $document))->assertNotFound();
        $this->actingAs($intruder)->delete(route('markdown.destroy', $document))->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'user_id' => $owner->id, 'title' => 'Owner secret']);
    }

    public function test_json_autosave_returns_a_new_version_and_rejects_a_stale_write(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['title' => 'Before']);
        $payload = ['title' => 'First saved value', 'markdown' => "# Saved body\n\nText", 'tags' => 'autosave', 'version' => 0];

        $this->actingAs($user)->patchJson(route('markdown.update', $document), $payload)->assertOk()
            ->assertJsonPath('document.id', $document->id)->assertJsonPath('document.title', 'First saved value')
            ->assertJsonPath('document.version', 1)->assertJsonPath('document.preview', "Saved body\n\nText")
            ->assertJsonPath('document.tags', ['autosave']);
        $this->actingAs($user)->patchJson(route('markdown.update', $document), [
            ...$payload,
            'title' => 'Stale overwrite',
        ])->assertStatus(409)->assertJsonPath('message', 'This document changed in another tab. Reload it before saving again.');

        $document->refresh();
        $this->assertSame('First saved value', $document->title);
        $this->assertSame(1, $document->lock_version);
        $this->assertSame("Saved body\n\nText", $document->content_text);
    }

    public function test_pinning_is_owned_versioned_and_sorts_the_documents_list_first(): void
    {
        $user = User::factory()->create();
        $older = Document::factory()->for($user)->create(['title' => 'Older document', 'updated_at' => now()->subMinute()]);
        $newer = Document::factory()->for($user)->create(['title' => 'Newer document']);

        $this->actingAs($user)->patch(route('markdown.pin', $older), ['version' => 0])
            ->assertRedirect(route('markdown.show', $older))->assertSessionHas('status', 'Document pinned.');
        $older->refresh();
        $this->assertTrue($older->is_pinned);
        $this->assertSame(1, $older->lock_version);
        $this->actingAs($user)->get(route('markdown.index'))->assertOk()
            ->assertSeeInOrder(['Older document', 'Newer document']);
        $this->actingAs($user)->patch(route('markdown.pin', $older), ['version' => 0])->assertSessionHasErrors('version');
    }

    public function test_export_returns_only_the_owners_raw_markdown_as_an_attachment(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'title' => 'Device Integration',
            'markdown' => "# Device Integration\n\nKeep this source exactly.",
        ]);

        $this->actingAs($user)->get(route('markdown.export', $document))->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="device-integration.md"')
            ->assertSeeText('Keep this source exactly.');
    }

    public function test_deleting_a_document_removes_its_pivot_rows_but_not_other_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $document = Document::factory()->for($user)->create();
        $tag = Tag::factory()->for($user)->create(['module' => 'markdown']);
        $document->tags()->attach($tag);
        $otherDocument = Document::factory()->for($other)->create();

        $this->actingAs($user)->delete(route('markdown.destroy', $document))->assertRedirect(route('markdown.index'));
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('document_tags', ['document_id' => $document->id]);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
        $this->assertDatabaseHas('documents', ['id' => $otherDocument->id]);
    }

    public function test_document_migration_rolls_back_its_pivot_before_documents_and_can_migrate_again(): void
    {
        $this->assertTrue(Schema::hasTable('documents'));
        $this->assertTrue(Schema::hasTable('document_tags'));
        // Projects is newer than Documents. Roll it and Documents to exercise
        // the latter's pivot-first down path without touching the Vault parent.
        Artisan::call('migrate:rollback', ['--step' => 2, '--no-interaction' => true]);
        $this->assertFalse(Schema::hasTable('documents'));
        $this->assertFalse(Schema::hasTable('document_tags'));
        $this->assertTrue(Schema::hasTable('tags'));
        Artisan::call('migrate', ['--no-interaction' => true]);
        $this->assertTrue(Schema::hasTable('documents'));
        $this->assertTrue(Schema::hasTable('document_tags'));
        $this->assertTrue(Schema::hasColumn('documents', 'lock_version'));
    }
}
