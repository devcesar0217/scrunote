@extends('layouts.app')
@section('title', 'Secure Vault · Scrunote')

@section('content')
    <section class="vault-workspace" x-data="vaultWorkspace({
        unlocked: {{ Illuminate\Support\Js::from($vaultUnlocked) }},
        idleMilliseconds: {{ Illuminate\Support\Js::from($idleTimeoutMinutes * 60 * 1000) }},
        lockUrl: {{ Illuminate\Support\Js::from(route('vault.lock')) }},
        heartbeatUrl: {{ Illuminate\Support\Js::from(route('vault.heartbeat')) }},
        csrfToken: {{ Illuminate\Support\Js::from(csrf_token()) }},
    })">
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
                    <a href="{{ route('vault.index', ['new' => 1]) }}" class="vault-primary-button"><x-icon name="plus" /> New entry</a>
                @endif
            </div>
        </header>

        @if (session('vault_status'))
            <p class="vault-flash" role="status"><x-icon name="circle-check" /> {{ session('vault_status') }}</p>
        @endif

        <div class="vault-layout">
            <aside class="vault-list-panel" aria-label="Your Vault entries">
                <form class="vault-search" method="GET" action="{{ route('vault.index') }}">
                    <label for="vault-search-query">Search Vault</label>
                    <div class="vault-search-input"><x-icon name="search" /><input id="vault-search-query" type="search" name="q" value="{{ $search }}" maxlength="120" placeholder="Name, URL, username, tag" autocomplete="off"></div>
                </form>

                @if ($vault)
                    <div class="vault-list-summary"><span>{{ $entries->count() }} {{ Str::plural('entry', $entries->count()) }}{{ $search !== '' ? ' found' : '' }}</span>@if ($search !== '')<a href="{{ route('vault.index') }}">Clear</a>@endif</div>
                    <div class="vault-list" aria-live="polite">
                        @forelse ($entries as $entry)
                            <a href="{{ route('vault.show', $entry) }}" class="vault-list-item {{ $selectedEntry?->is($entry) ? 'is-selected' : '' }}" @if ($selectedEntry?->is($entry)) aria-current="page" @endif>
                                <span class="vault-list-title">{{ $entry->title }} @if ($entry->is_pinned)<x-icon name="pin-filled" aria-label="Pinned" />@endif</span>
                                @if ($entry->username)<span class="vault-list-user">{{ $entry->username }}</span>@endif
                                @if ($entry->tags->isNotEmpty())<span class="vault-list-tags">{{ $entry->tags->map(fn ($tag) => '#'.$tag->name)->implode(' ') }}</span>@endif
                                <time datetime="{{ $entry->updated_at->toAtomString() }}">{{ $entry->updated_at->diffForHumans() }}</time>
                            </a>
                        @empty
                            <div class="vault-list-empty"><x-icon name="shield-lock" /><p>{{ $search !== '' ? 'No entries match this search.' : 'No Vault entries yet.' }}</p></div>
                        @endforelse
                    </div>
                @else
                    <div class="vault-list-empty"><x-icon name="lock" /><p>Set up your Vault to begin storing encrypted credentials.</p></div>
                @endif
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
                        <a href="{{ route('vault.index', ['new' => 1]) }}" class="vault-subtle-button"><x-icon name="plus" /> Create your first entry</a>
                    </section>
                @endif
            </section>
        </div>
    </section>
@endsection
