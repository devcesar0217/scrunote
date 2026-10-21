<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Note;
use App\Models\Project;
use App\Models\VaultEntry;
use App\Policies\DocumentPolicy;
use App\Policies\NotePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\VaultEntryPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Laravel merges its own mailer defaults into app config. Keep only
        // the inert in-memory transport for this login/registration-only scope.
        $this->app['config']->set('mail.mailers', [
            'array' => $this->app['config']->get('mail.mailers.array'),
        ]);
    }

    public function boot(): void
    {
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(VaultEntry::class, VaultEntryPolicy::class);
        RateLimiter::for('login-ip', fn (Request $request) => Limit::perMinute(30)->by((string) $request->ip()));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('vault-unlock', fn (Request $request) => Limit::perMinute(8)->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));
        // Password-derived backup encryption is intentionally costly. Limit
        // both creation and restore work per signed-in account/IP pair.
        RateLimiter::for('workspace-backup', fn (Request $request) => Limit::perMinute(4)->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));
    }
}
