@extends('layouts.app')
@section('title', ($selectedNote ? $selectedNote->display_title.' · ' : '').'Notes · Scrunote')

@section('content')
    <section class="notes-workspace" x-data="notesWorkspace({ selected: {{ Illuminate\Support\Js::from($selectedNote?->id) }} })"
        @keydown.window="handleShortcut($event)" @notes-created.window="openList()">
        <header class="notes-page-header">
            <div>
                <h1>Notes</h1>
                <p>Capture and organize rich-text notes in your signed-in workspace.</p>
            </div>
            <div class="notes-page-actions">
                <button type="button" class="workspace-secondary-button notes-list-toggle" @click="toggleList()" :aria-expanded="listOpen" aria-controls="notes-list-panel">
                    <x-icon name="{{ $selectedNote ? 'layout-sidebar-left-collapse' : 'list' }}" />
                    <span x-text="listOpen ? 'Hide list' : 'Show list'">Show list</span>
                </button>
                <form method="POST" action="{{ route('notes.store') }}">
                    @csrf
                    <button type="submit" class="notes-primary-button"><x-icon name="plus" /> New note</button>
                </form>
            </div>
        </header>

        @if (session('status'))
            <p class="notes-flash" role="status"><x-icon name="circle-check" /> {{ session('status') }}</p>
        @endif

        <div class="notes-layout" :class="{ 'notes-list-is-hidden': !listOpen }">
            <aside id="notes-list-panel" class="notes-list-panel" aria-label="Your notes" :aria-hidden="!listOpen" :inert="!listOpen">
                <form class="notes-search" method="GET" action="{{ route('notes.index') }}">
                    <label for="notes-search-query">Search notes</label>
                    <div class="notes-search-input">
                        <x-icon name="search" />
                        <input id="notes-search-query" type="search" name="q" value="{{ $search }}" maxlength="120"
                            placeholder="Title, content, or tag" autocomplete="off">
                    </div>
                </form>

                <div class="notes-list-summary">
                    <span>{{ $notes->count() }} {{ Str::plural('note', $notes->count()) }}{{ $search !== '' ? ' found' : '' }}</span>
                    @if ($search !== '')<a href="{{ route('notes.index') }}">Clear</a>@endif
                </div>

                <div class="notes-list" aria-live="polite">
                    @forelse ($notes as $note)
                        <a href="{{ route('notes.show', $note) }}" class="notes-list-item {{ $selectedNote?->is($note) ? 'is-selected' : '' }}" data-note-id="{{ $note->id }}"
                            @click="closeListForSelection()" @if ($selectedNote?->is($note)) aria-current="page" @endif>
                            <span class="notes-list-title"><span data-note-list-title>{{ $note->display_title }}</span>@if ($note->is_pinned)<x-icon name="pin-filled" aria-label="Pinned" />@endif</span>
                            <span class="notes-list-tags" data-note-list-tags @if ($note->tags->isEmpty()) hidden @endif>{{ $note->tags->map(fn ($tag) => '#'.$tag->name)->implode(' ') }}</span>
                            <span class="notes-list-preview" data-note-list-preview>{{ filled($note->content_text) ? Str::limit($note->content_text, 58) : 'Empty note' }}</span>
                            <time data-note-list-updated datetime="{{ $note->updated_at->toAtomString() }}">{{ $note->updated_at->diffForHumans() }}</time>
                        </a>
                    @empty
                        <div class="notes-list-empty">
                            <x-icon name="notes" />
                            <p>{{ $search !== '' ? 'No notes match this search.' : 'No notes yet.' }}</p>
                            @if ($search !== '')<a href="{{ route('notes.index') }}">Clear search</a>@endif
                        </div>
                    @endforelse
                </div>

                @if ($notes->previousPageUrl() || $notes->nextPageUrl())
                    <nav class="notes-pagination" aria-label="Notes pages">
                        @if ($notes->previousPageUrl())<a href="{{ $notes->previousPageUrl() }}">Previous</a>@else<span>Previous</span>@endif
                        @if ($notes->nextPageUrl())<a href="{{ $notes->nextPageUrl() }}">Next</a>@else<span>Next</span>@endif
                    </nav>
                @endif
            </aside>

            <section class="notes-editor-panel" aria-label="Note editor">
                @if ($selectedNote)
                    @include('pages.notes.editor')
                @else
                    <div class="notes-empty-state">
                        <span class="notes-empty-icon"><x-icon name="notes" /></span>
                        <h2>Choose a note or start fresh</h2>
                        <p>Create a private rich-text note, then search it by title, text or tags.</p>
                        <form method="POST" action="{{ route('notes.store') }}">
                            @csrf
                            <button type="submit" class="workspace-secondary-button"><x-icon name="plus" /> Create your first note</button>
                        </form>
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
