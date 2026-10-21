<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Alex',
            'last_name' => 'Morgan',
            'username' => 'alex.morgan',
            'email' => 'alex@example.com',
            'password' => 'a-unique-passphrase-123',
            'password_confirmation' => 'a-unique-passphrase-123',
        ], $overrides);
    }

    public function test_private_home_requires_authentication(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authentication_views_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Username or email')->assertDontSee('Forgot password?');
        $this->get(route('register'))->assertOk()->assertSee('First name')->assertSee('Last name')->assertSee('Username');
    }

    public function test_recovery_routes_and_outbound_mail_configuration_are_removed(): void
    {
        foreach (['password.request', 'password.email', 'password.reset', 'password.update'] as $name) {
            $this->assertFalse(Route::has($name));
        }
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'alex@example.com'])->assertNotFound();
        $this->get('/reset-password/removed-token')->assertNotFound();
        $this->get('/reset-password')->assertNotFound();
        $this->post('/reset-password', ['token' => 'removed-token'])->assertNotFound();
        $this->assertNull(config('auth.passwords'));
        $this->assertSame('array', config('mail.default'));
        $this->assertSame(['array'], array_keys(config('mail.mailers')));
    }

    public function test_registration_and_login_work_without_a_delivery_mailer(): void
    {
        Notification::fake();
        config(['mail.default' => 'deliberately-unconfigured']);

        $this->post(route('register.store'), $this->registration())->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['login' => 'alex.morgan', 'password' => 'a-unique-passphrase-123'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        Notification::assertNothingSent();
    }

    public function test_a_user_can_register_with_the_existing_schema(): void
    {
        $this->withSession(['anonymous_marker' => 'kept']);
        $oldId = session()->getId();
        $oldToken = session()->token();

        $this->post(route('register.store'), $this->registration([
            'username' => '  Alex.Morgan  ', 'email' => '  Alex@EXAMPLE.com ',
            'is_active' => false, 'email_verified_at' => now()->toISOString(),
        ]))->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('alex.morgan', $user->username);
        $this->assertSame('alex@example.com', $user->email);
        $this->assertSame('Alex Morgan', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('a-unique-passphrase-123', $user->password));
        $this->assertNotSame('a-unique-passphrase-123', $user->password);
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->get('/')->assertOk()->assertSee('Alex Morgan')->assertSee('alex@example.com');
    }

    public function test_registration_rejects_invalid_fields_and_does_not_flash_passwords(): void
    {
        $this->from(route('register'))->post(route('register.store'), $this->registration([
            'first_name' => '', 'username' => 'not allowed!', 'email' => 'not-an-email',
            'password' => 'short', 'password_confirmation' => 'different',
        ]))->assertRedirect(route('register'))->assertSessionHasErrors(['first_name', 'username', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('register'))->assertOk()->assertSee('Please check the highlighted fields');
    }

    public function test_registration_rejects_case_insensitive_duplicate_email_and_username(): void
    {
        User::factory()->create(['email' => 'alex@example.com', 'username' => 'alex.morgan']);
        $this->post(route('register.store'), $this->registration([
            'email' => 'ALEX@EXAMPLE.COM', 'username' => 'ALEX.MORGAN',
        ]))->assertSessionHasErrors(['email', 'username']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_requires_confirmation_and_a_long_enough_passphrase(): void
    {
        $this->post(route('register.store'), $this->registration(['password_confirmation' => 'not-the-same-123']))
            ->assertSessionHasErrors('password');
        $this->post(route('register.store'), $this->registration(['password' => '12345678', 'password_confirmation' => '12345678']))
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_bcrypt_passwords_are_not_silently_truncated(): void
    {
        $password = str_repeat('é', 37); // 37 characters, but 74 bytes.
        $this->post(route('register.store'), $this->registration([
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_null_bytes_are_rejected_before_hashing(): void
    {
        $password = "valid-length-123\0suffix";
        $this->post(route('register.store'), $this->registration([
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertSessionHasErrors('password');
    }

    public function test_malformed_array_inputs_return_validation_errors_not_type_errors(): void
    {
        $this->from(route('register'))->post(route('register.store'), $this->registration([
            'email' => ['invalid'], 'username' => ['invalid'], 'first_name' => ['invalid'],
        ]))->assertSessionHasErrors(['email', 'username', 'first_name']);
        $this->get(route('register'))->assertOk();
        $this->from(route('login'))->post(route('login.store'), ['login' => ['invalid'], 'password' => 'test-password-123'])
            ->assertSessionHasErrors('login');
        $this->get(route('login'))->assertOk();
    }

    public function test_registration_can_be_disabled_for_get_and_post(): void
    {
        config(['auth.registration_enabled' => false]);
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), $this->registration())->assertForbidden();
        $this->get(route('login'))->assertDontSee('Create account');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_user_can_login_by_email_and_the_session_is_regenerated(): void
    {
        $user = User::factory()->create(['email' => 'alex@example.com']);
        $this->withSession(['marker' => 'kept']);
        $oldId = session()->getId();
        $oldToken = session()->token();

        $this->post(route('login.store'), ['login' => '  ALEX@example.com ', 'password' => 'test-password-123'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->get('/')->assertOk()->assertSee($user->name);
    }

    public function test_a_user_can_login_by_username(): void
    {
        $user = User::factory()->create(['username' => 'alex.morgan']);
        $this->post(route('login.store'), ['login' => 'ALEX.MORGAN', 'password' => 'test-password-123'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_and_unknown_user_have_the_same_error(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'incorrect-password'])
            ->assertSessionHasErrors(['login' => 'The supplied credentials could not be verified.']);
        $this->assertGuest();
        $this->post(route('login.store'), ['login' => 'missing@example.com', 'password' => 'incorrect-password'])
            ->assertSessionHasErrors(['login' => 'The supplied credentials could not be verified.']);
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_inactive_users_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'test-password-123'])
            ->assertSessionHasErrors(['login' => 'The supplied credentials could not be verified.']);
        $this->assertGuest();
    }

    public function test_five_failed_attempts_lock_out_the_identifier_temporarily(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['login' => $user->email, 'password' => 'incorrect-password'])
                ->assertSessionHasErrors('login');
        }
        $response = $this->post(route('login.store'), ['login' => $user->email, 'password' => 'test-password-123']);
        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_a_successful_login_clears_the_failed_attempt_counter(): void
    {
        $user = User::factory()->create();
        $key = 'login:'.hash('sha256', $user->email.'|127.0.0.1');
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'incorrect-password']);
        $this->assertSame(1, RateLimiter::attempts($key));
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'test-password-123']);
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_remember_me_sets_a_persistent_authentication_cookie(): void
    {
        $user = User::factory()->create();
        $response = $this->post(route('login.store'), ['login' => $user->email, 'password' => 'test-password-123', 'remember' => '1']);
        $response->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_logout_invalidates_session_data_and_rotates_the_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private_marker' => 'must disappear']);
        $oldId = session()->getId();
        $oldToken = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('private_marker'));
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_logout_does_not_accept_a_get_request(): void
    {
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_authenticated_users_are_redirected_away_from_guest_forms(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['login', 'register'] as $route) {
            $this->get(route($route))->assertRedirect(route('dashboard'));
        }
    }

    public function test_a_disabled_account_loses_access_to_an_existing_session(): void
    {
        $this->actingAs(User::factory()->inactive()->create())->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_session_with_an_old_password_hash_is_rejected(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $user->forceFill(['password' => 'a-new-strong-password-123'])->save();
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_private_checkpoint_contains_only_the_current_account(): void
    {
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        User::factory()->create(['email' => 'bob@example.com']);
        $this->actingAs($alice)->get('/')->assertOk()->assertSee('alice@example.com')->assertDontSee('bob@example.com')
            ->assertDontSee('14 Stored Keys')->assertDontSee('Zero-Knowledge');
    }

    public function test_user_names_are_escaped_in_the_signed_in_view(): void
    {
        $user = User::factory()->create(['first_name' => '<script>alert(1)</script>']);
        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_authentication_responses_are_not_cacheable_and_do_not_leak_referrers(): void
    {
        $response = $this->get(route('login'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response = $this->actingAs(User::factory()->create())->get('/');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_real_csrf_middleware_rejects_a_missing_token_outside_testing_bypass(): void
    {
        $this->app['env'] = 'local';
        $this->post(route('login.store'), ['login' => 'alex@example.com', 'password' => 'test-password-123'])->assertStatus(419);
    }
}
