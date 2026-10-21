@extends('layouts.app')
@section('title', ($selectedDocument ? $selectedDocument->display_title.' · ' : '').'Markdown Docs · Scrunote')

@section('content')
    <section class="markdown-workspace" x-data="markdownWorkspace({ selected: {{ Illuminate\Support\Js::from($selectedDocument?->id) }} })"
        @keydown.window="handleShortcut($event)">
        <header class="markdown-page-header">
            <div>
                <h1>Markdown Docs</h1>
                <p>Write, preview, and organize developer documentation in one private workspace.</p>
            </div>
            <div class="markdown-page-actions">
                <button type="button" class="workspace-secondary-button markdown-list-toggle" @click="toggleList()" :aria-expanded="listOpen" aria-controls="markdown-list-panel">
                    <x-icon name="{{ $selectedDocument ? 'layout-sidebar-left-collapse' : 'list' }}" />
                    <span x-text="listOpen ? 'Hide list' : 'Show list'">Show list</span>
                </button>
                <form method="POST" action="{{ route('markdown.store') }}">
                    @csrf
                    <button type="submit" class="markdown-primary-button"><x-icon name="plus" /> New document</button>
                </form>
            </div>
        </header>

        @if (session('status'))
            <p class="markdown-flash" role="status"><x-icon name="circle-check" /> {{ session('status') }}</p>
        @endif

        <div class="markdown-layout" :class="{ 'markdown-list-is-hidden': !listOpen }">
            <aside id="markdown-list-panel" class="markdown-list-panel" aria-label="Your Markdown documents" :aria-hidden="!listOpen" :inert="!listOpen">
                <form class="markdown-search" method="GET" action="{{ route('markdown.index') }}">
                    <label for="markdown-search-query">Search documents</label>
                    <div class="markdown-search-input">
                        <x-icon name="search" />
                        <input id="markdown-search-query" type="search" name="q" value="{{ $search }}" maxlength="120"
                            placeholder="Title, content, or tag" autocomplete="off">
                    </div>
                </form>

                <div class="markdown-list-summary">
                    <span>{{ $documents->count() }} {{ Str::plural('document', $documents->count()) }}{{ $search !== '' ? ' found' : '' }}</span>
                    @if ($search !== '')<a href="{{ route('markdown.index') }}">Clear</a>@endif
                </div>

                <div class="markdown-list" aria-live="polite">
                    @forelse ($documents as $document)
                        <a href="{{ route('markdown.show', $document) }}" class="markdown-list-item {{ $selectedDocument?->is($document) ? 'is-selected' : '' }}" data-document-id="{{ $document->id }}"
                            @click="closeListForSelection()" @if ($selectedDocument?->is($document)) aria-current="page" @endif>
                            <span class="markdown-list-title"><span data-document-list-title>{{ $document->display_title }}</span>@if ($document->is_pinned)<x-icon name="pin-filled" aria-label="Pinned" />@endif</span>
                            <span class="markdown-list-tags" data-document-list-tags @if ($document->tags->isEmpty()) hidden @endif>{{ $document->tags->map(fn ($tag) => '#'.$tag->name)->implode(' ') }}</span>
                            <span class="markdown-list-preview" data-document-list-preview>{{ filled($document->content_text) ? Str::limit($document->content_text, 58) : 'Empty document' }}</span>
                            <time data-document-list-updated datetime="{{ $document->updated_at->toAtomString() }}">{{ $document->updated_at->diffForHumans() }}</time>
                        </a>
                    @empty
                        <div class="markdown-list-empty">
                            <x-icon name="markdown" />
                            <p>{{ $search !== '' ? 'No documents match this search.' : 'No documents yet.' }}</p>
                            @if ($search !== '')<a href="{{ route('markdown.index') }}">Clear search</a>@endif
                        </div>
                    @endforelse
                </div>

                @if ($documents->previousPageUrl() || $documents->nextPageUrl())
                    <nav class="markdown-pagination" aria-label="Document pages">
                        @if ($documents->previousPageUrl())<a href="{{ $documents->previousPageUrl() }}">Previous</a>@else<span>Previous</span>@endif
                        @if ($documents->nextPageUrl())<a href="{{ $documents->nextPageUrl() }}">Next</a>@else<span>Next</span>@endif
                    </nav>
                @endif
            </aside>

            <section class="markdown-editor-panel" aria-label="Markdown document editor">
                @if ($selectedDocument)
                    @include('pages.markdown.editor')
                @else
                    <div class="markdown-empty-state">
                        <span class="markdown-empty-icon"><x-icon name="markdown" /></span>
                        <h2>Choose a document or start fresh</h2>
                        <p>Create a private Markdown document with an editor and safe live preview.</p>
                        <form method="POST" action="{{ route('markdown.store') }}">
                            @csrf
                            <button type="submit" class="workspace-secondary-button"><x-icon name="plus" /> Create your first document</button>
                        </form>
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
