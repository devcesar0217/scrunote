<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    public static function shellPages(): array
    {
        return [
            'overview' => ['/', 'overview', 'Overview', 'pages.dashboard'],
            'notes' => ['/notes', 'notes', 'Notes', 'pages.notes.index'],
            'markdown' => ['/markdown', 'markdown', 'Markdown Docs', 'pages.markdown.index'],
            'vault' => ['/vault', 'vault', 'Secure Vault', 'pages.vault.index'],
            'search' => ['/search', 'search', 'Global Search', 'pages.search.index'],
            'settings' => ['/settings', 'settings', 'Settings', 'pages.section-unavailable'],
        ];
    }

    public static function unavailableSections(): array
    {
        return [
            'settings' => ['/settings', 'settings', 'Phase 8'],
        ];
    }

    #[DataProvider('shellPages')]
    public function test_every_shell_page_requires_authentication(string $path, string $id, string $label, string $view): void
    {
        $this->get($path)->assertRedirect(route('login'));
    }

    #[DataProvider('shellPages')]
    public function test_every_shell_page_uses_the_shared_private_layout(string $path, string $id, string $label, string $view): void
    {
        $user = User::factory()->create(['first_name' => 'Alex', 'last_name' => 'Morgan']);
        $response = $this->actingAs($user)->get($path)->assertOk()
            ->assertViewIs($view)
            ->assertViewHas('page', fn (array $page): bool => $page['id'] === $id)
            ->assertSee($label)->assertSee('Alex Morgan')->assertSee($user->email)
            ->assertSee('Skip to content')->assertSee('Global Search')->assertSee('Search your workspace')
            ->assertSee('Sign out')->assertSee('Web account')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertDontSee($user->password, false)->assertDontSee('href="#"', false);
    }

    public function test_login_returns_to_the_protected_notes_section_the_guest_requested(): void
    {
        $user = User::factory()->create();
        $this->get('/notes')->assertRedirect(route('login'));
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post(route('login.store'), ['login' => $user->username, 'password' => 'test-password-123'])
            ->assertSessionHasNoErrors()->assertRedirect(url('/notes'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_navigation_matches_the_reference_and_only_has_real_relative_destinations(): void
    {
        $items = WorkspaceNavigation::items();
        $this->assertSame(['Overview', 'Notes', 'Markdown Docs', 'Secure Vault', 'Settings'], array_column($items, 'label'));
        $this->assertSame(['/', '/notes', '/markdown', '/vault', '/settings'], array_column($items, 'url'));
        foreach ($items as $item) {
            $this->assertStringStartsWith('/', $item['url']);
            $this->assertStringStartsNotWith('//', $item['url']);
            $this->assertArrayNotHasKey('email', $item);
            $this->assertArrayNotHasKey('user_id', $item);
        }
    }

    #[DataProvider('unavailableSections')]
    public function test_future_modules_remain_honest_unavailable_pages_without_crud_controls(string $path, string $id, string $phase): void
    {
        $this->actingAs(User::factory()->create())->get($path)->assertOk()
            ->assertSee('Not implemented yet')->assertSee($phase)
            ->assertSee('No module records are created, searched or stored in Phase 3.')
            ->assertSee('Back to overview')
            ->assertDontSee('<textarea', false)->assertDontSee('type="file"', false)
            ->assertDontSee('name="content"', false)->assertDontSee('Export Excel')
            ->assertDontSee('Create backup');
    }

    public function test_unavailable_settings_mutation_endpoints_are_not_exposed(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/settings'] as $path) {
            foreach (['post', 'put', 'patch', 'delete'] as $method) {
                $this->{$method}($path, ['title' => 'Must not be saved'])->assertMethodNotAllowed();
            }
            $this->get($path.'/123')->assertNotFound();
        }
    }

    public function test_unavailable_settings_page_does_not_query_module_records(): void
    {
        $user = User::factory()->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($user)->get('/settings')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(notes|note_tags|tags|documents|vault_entries|vaults)\b/i', $query['query']);
        }
    }

    public function test_overview_only_shows_the_current_users_real_notes(): void
    {
        $alice = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Current', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Other', 'email' => 'bob@example.com']);
        Note::factory()->for($alice)->create(['title' => 'Alice actual note']);
        Note::factory()->for($bob)->create(['title' => 'Bob private note']);

        $this->actingAs($alice)->get('/')->assertOk()
            ->assertSee('1')->assertSee('Alice actual note')->assertDontSee('Bob private note')
            ->assertSee('Recent notes')->assertSee('New note')
            ->assertDontSee('14 Stored Keys')->assertDontSee('Zero-Knowledge')
            ->assertDontSee('All systems encrypted and in sync.');
    }

    public function test_shell_identity_always_comes_from_the_current_session(): void
    {
        $alice = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Current', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Other', 'email' => 'bob@example.com']);
        foreach (array_column(self::shellPages(), 0) as $path) {
            $this->actingAs($alice)->get($path.'?user_id='.$bob->id)->assertOk()
                ->assertSee('Alice Current')->assertSee('alice@example.com')
                ->assertDontSee('Bob Other')->assertDontSee('bob@example.com');
        }
    }

    public function test_inactive_users_cannot_access_any_shell_section(): void
    {
        $user = User::factory()->inactive()->create();
        foreach (array_column(self::shellPages(), 0) as $path) {
            $this->actingAs($user)->get($path)->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_identity_is_escaped_in_headings_menus_and_accessible_labels(): void
    {
        $user = User::factory()->create(['first_name' => '<script>alert(1)</script>', 'last_name' => '" onmouseover="alert(2)']);
        foreach (array_column(self::shellPages(), 0) as $path) {
            $this->actingAs($user)->get($path)->assertOk()
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertDontSee('name="" onmouseover="alert(2)', false)
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        }
    }
}
