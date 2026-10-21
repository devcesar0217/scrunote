<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    public static function shellPages(): array
    {
        return [
            'overview' => ['/', 'overview', 'Overview', 'pages.dashboard'],
            'projects' => ['/projects', 'projects', 'Projects', 'pages.projects.index'],
            'notes' => ['/notes', 'notes', 'Notes', 'pages.notes.index'],
            'markdown' => ['/markdown', 'markdown', 'Markdown Docs', 'pages.markdown.index'],
            'vault' => ['/vault', 'vault', 'Secure Vault', 'pages.vault.index'],
            'search' => ['/search', 'search', 'Global Search', 'pages.search.index'],
            'settings' => ['/settings', 'settings', 'Settings', 'pages.settings.index'],
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
        $this->assertSame(['Overview', 'Projects', 'Notes', 'Markdown Docs', 'Secure Vault', 'Settings'], array_column($items, 'label'));
        $this->assertSame(['/', '/projects', '/notes', '/markdown', '/vault', '/settings'], array_column($items, 'url'));
        foreach ($items as $item) {
            $this->assertStringStartsWith('/', $item['url']);
            $this->assertStringStartsNotWith('//', $item['url']);
            $this->assertArrayNotHasKey('email', $item);
            $this->assertArrayNotHasKey('user_id', $item);
        }
    }

    public function test_settings_has_only_its_explicit_backup_mutation_endpoints(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/settings', ['title' => 'Must not be saved'])->assertMethodNotAllowed();
        $this->put('/settings')->assertMethodNotAllowed();
        $this->patch('/settings')->assertMethodNotAllowed();
        $this->delete('/settings')->assertMethodNotAllowed();
        $this->get('/settings/123')->assertNotFound();
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
