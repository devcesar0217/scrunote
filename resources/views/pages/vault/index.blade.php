@extends('layouts.app')
@section('title', 'Secure Vault · Scrunote')

@section('content')
    <section class="vault-workspace" x-data="vaultWorkspace({
        unlocked: {{ Illuminate\Support\Js::from($vaultUnlocked) }},
        idleMilliseconds: {{ Illuminate\Support\Js::from($idleTimeoutMinutes * 60 * 1000) }},
        lockUrl: {{ Illuminate\Support\Js::from(route('vault.lock')) }},
        heartbeatUrl: {{ Illuminate\Support\Js::from(route('vault.heartbeat')) }},
        csrfToken: {{ Illuminate\Support\Js::from(csrf_token()) }},
        projectId: {{ Illuminate\Support\Js::from(is_int($projectFilter) ? $projectFilter : null) }},
        newEntryUrl: {{ Illuminate\Support\Js::from(route('vault.index', ['new' => 1])) }},
    })" @workspace-project-filter-updated.window="if ($event.detail.module === 'vault') projectId = $event.detail.projectId">
        <header class="vault-page-header">
            <div>
                <h1>Secure Vault</h1>
                <p>Credential secrets are encrypted at rest and unlock only with your Vault master password.</p>
            </div>
            <div class="vault-page-actions">
                @if ($vaultUnlocked)
                    <form method="POST" action="{{ route('vault.lock') }}">
                        @csrf
                        <button type="submit" class="vault-subtle-button"><x-icon name="lock" /> Lock Vault</button>
                    </form>
                    <a href="{{ route('vault.index', array_filter(['new' => 1, 'project' => is_int($projectFilter) ? $projectFilter : null])) }}" :href="newEntryUrlForProject" class="vault-primary-button"><x-icon name="plus" /> New entry</a>
                @endif
            </div>
        </header>

        @if (session('vault_status'))
            <p class="vault-flash" role="status"><x-icon name="circle-check" /> {{ session('vault_status') }}</p>
        @endif

        <div class="vault-layout">
            <aside class="vault-list-panel" aria-label="Your Vault entries">
                <livewire:vault-search :selected-id="$selectedEntry?->id" :initial-search="$search" :initial-project-filter="$projectFilter" />
            </aside>

            <section class="vault-editor-panel" aria-label="Vault entry workspace">
                @if ($vault === null)
                    <section class="vault-setup-card">
                        <span class="vault-empty-icon"><x-icon name="shield-lock" /></span>
                        <p class="vault-kicker">First-time setup</p>
                        <h2>Create your Vault master password</h2>
                        <p>This password is separate from your account password. It derives the key that unlocks your encrypted Vault. If it is forgotten, existing secrets cannot be recovered.</p>
                        <form method="POST" action="{{ route('vault.setup') }}" class="vault-unlock-form">
                            @csrf
                            <label>Vault master password<input type="password" name="master_password" minlength="12" maxlength="1024" autocomplete="new-password" required></label>
                            <label>Confirm master password<input type="password" name="master_password_confirmation" minlength="12" maxlength="1024" autocomplete="new-password" required></label>
                            @error('master_password')<p class="vault-field-error">{{ $message }}</p>@enderror
                            <button type="submit" class="vault-primary-button"><x-icon name="shield-check" /> Create and unlock Vault</button>
                        </form>
                    </section>
                @elseif (! $vaultUnlocked)
                    <section class="vault-setup-card">
                        <span class="vault-empty-icon vault-locked-icon"><x-icon name="lock" /></span>
                        <p class="vault-kicker">Vault locked</p>
                        <h2>Unlock to access credential secrets</h2>
                        <p>Entry names and tags remain private to your account. Secret values, encrypted notes, and all Vault changes require your master password.</p>
                        <form method="POST" action="{{ route('vault.unlock') }}" class="vault-unlock-form">
                            @csrf
                            <label>Vault master password<input type="password" name="master_password" maxlength="1024" autocomplete="current-password" required autofocus></label>
                            @error('master_password')<p class="vault-field-error">{{ $message }}</p>@enderror
                            <button type="submit" class="vault-primary-button"><x-icon name="lock-open" /> Unlock Vault</button>
                        </form>
                        <p class="vault-lock-note">For protection, an unlocked Vault locks automatically after {{ $idleTimeoutMinutes }} minutes without activity.</p>
                    </section>
                @elseif (($createRequested ?? false) || ($editRequested ?? false))
                    @include('pages.vault.entry-form')
                @elseif ($selectedEntry)
                    @include('pages.vault.detail')
                @else
                    <section class="vault-empty-state">
                        <span class="vault-empty-icon"><x-icon name="shield-lock" /></span>
                        <h2>Choose an entry or add a new one</h2>
                        <p>Secret values are never shown in this workspace until you explicitly reveal or copy them.</p>
                        <a href="{{ route('vault.index', array_filter(['new' => 1, 'project' => is_int($projectFilter) ? $projectFilter : null])) }}" :href="newEntryUrlForProject" class="vault-subtle-button"><x-icon name="plus" /> Create your first entry</a>
                    </section>
                @endif
            </section>
        </div>
    </section>
@endsection
