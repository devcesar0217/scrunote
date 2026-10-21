@extends('layouts.app')
@section('title', 'Overview · Scrunote')

@section('content')
    <section class="workspace-hero" aria-labelledby="overview-heading">
        <div class="workspace-hero-copy">
            <p class="workspace-eyebrow">Personal developer workspace</p>
            <h1 id="overview-heading">Welcome back, {{ $identity['first_name'] }}.</h1>
            <p class="workspace-hero-description">Keep ideas, Markdown references, and account credentials organized in one private place.</p>
            <p class="workspace-hero-meta">Your Notes are connected. Other modules follow in their approved phases.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="workspace-secondary-button workspace-refresh" aria-label="Refresh overview">
            <x-icon name="refresh" /> Refresh
        </a>
    </section>

    <section aria-labelledby="workspace-sections-heading">
        <div class="workspace-section-heading">
            <h2 id="workspace-sections-heading">Your workspace</h2>
            <p>Select a card to open that section</p>
        </div>
        <div class="workspace-module-grid">
            @foreach ($modules as $module)
                <a href="{{ $module['url'] }}" class="workspace-module-card" data-module="{{ $module['id'] }}">
                    <span class="workspace-module-icon"><x-icon :name="$module['icon']" /></span>
                    <x-icon name="arrow-up-right" class="workspace-module-arrow" />
                    @if ($module['id'] === 'notes')
                        <span class="workspace-module-count">{{ $notesCount }}</span>
                        <span class="workspace-module-label">{{ Str::plural('Note', $notesCount) }}</span>
                        <span class="workspace-module-phase">Connected</span>
                    @else
                        <span class="workspace-module-count" aria-hidden="true">—</span>
                        <span class="sr-only">Count not available.</span>
                        <span class="workspace-module-label">{{ $module['label'] === 'Secure Vault' ? 'Password Vault Entries' : $module['label'] }}</span>
                        <span class="workspace-module-phase">{{ $module['phase'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    <div class="workspace-overview-panels">
        <section class="workspace-panel workspace-activity" aria-labelledby="activity-heading">
            <header class="workspace-panel-heading">
                <div>
                    <h2 id="activity-heading">Recent notes</h2>
                    <p>Your latest saved Notes changes</p>
                </div>
                <span class="workspace-status-badge">{{ $notesCount }} saved</span>
            </header>
            @if ($recentNotes->isNotEmpty())
                <div class="workspace-activity-list">
                    @foreach ($recentNotes as $note)
                        <a href="{{ route('notes.show', $note) }}" class="workspace-activity-item">
                            <span class="workspace-activity-note-icon"><x-icon name="file-description" /></span>
                            <span class="workspace-activity-copy"><strong>{{ $note->display_title }}</strong><span>Updated {{ $note->updated_at->diffForHumans() }}</span></span>
                            <x-icon name="chevron-right" />
                        </a>
                    @endforeach
                </div>
            @else
                <div class="workspace-activity-empty">
                    <span class="workspace-empty-icon"><x-icon name="notes" /></span>
                    <h3>No notes yet</h3>
                    <p>Create a note to start building your personal workspace.</p>
                </div>
            @endif
            <p class="workspace-activity-footer">Markdown Docs and Secure Vault are not connected yet.</p>
        </section>

        <section class="workspace-panel workspace-quick-actions" aria-labelledby="quick-actions-heading">
            <header class="workspace-panel-heading">
                <div>
                    <h2 id="quick-actions-heading">Quick actions</h2>
                    <p>Start with your active workspace tools</p>
                </div>
            </header>
            <div class="workspace-quick-links">
                <form method="POST" action="{{ route('notes.store') }}">
                    @csrf
                    <button type="submit" class="workspace-quick-link workspace-quick-link-button" data-module="notes">
                        <span class="workspace-quick-icon"><x-icon name="plus" /></span>
                        <span class="workspace-quick-copy"><strong>New note</strong><span>Create a saved rich-text note</span></span>
                        <x-icon name="chevron-right" class="workspace-quick-arrow" />
                    </button>
                </form>
                @foreach (collect($modules)->where('id', '!=', 'notes') as $module)
                    <a href="{{ $module['url'] }}" class="workspace-quick-link" data-module="{{ $module['id'] }}">
                        <span class="workspace-quick-icon"><x-icon :name="$module['icon']" /></span>
                        <span class="workspace-quick-copy"><strong>Open {{ $module['label'] }}</strong><span>Section preview · {{ $module['phase'] }}</span></span>
                        <x-icon name="chevron-right" class="workspace-quick-arrow" />
                    </a>
                @endforeach
            </div>
        </section>
    </div>
@endsection
