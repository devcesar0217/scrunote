<div class="markdown-live-search">
    <form class="markdown-search" method="GET" action="{{ route('markdown.index') }}" wire:submit.prevent>
        <label for="markdown-search-query">Search documents</label>
        <div class="markdown-search-input">
            <x-icon name="search" />
            <input id="markdown-search-query" type="search" name="q" wire:model.live.debounce.300ms="search" maxlength="120"
                placeholder="Title, content, or tag" autocomplete="off">
        </div>
        <label class="markdown-project-filter" for="markdown-project-filter"><x-icon name="folder" /><span>Location</span>
            <select id="markdown-project-filter" name="project" wire:model.live="projectFilter">
                <option value="">All workspace</option>
                <option value="mine">My Workspace</option>
                @foreach ($projects as $project)<option value="{{ $project->id }}">{{ $project->title }}</option>@endforeach
            </select>
        </label>
        <noscript><button type="submit" class="sr-only">Apply Markdown search</button></noscript>
    </form>

    <div class="markdown-list-summary">
        <span>{{ $documents->count() }} {{ Str::plural('document', $documents->count()) }}{{ $search !== '' ? ' found' : '' }}</span>
        @if ($search !== '')<button type="button" wire:click="$set('search', '')">Clear</button>@endif
        <span class="markdown-search-progress" wire:loading wire:target="search,projectFilter">Updating…</span>
    </div>

    <div class="markdown-list" aria-live="polite" wire:loading.class="is-loading" wire:target="search,projectFilter">
        @forelse ($documents as $document)
            <a href="{{ route('markdown.show', array_filter(['document' => $document, 'project' => $projectFilter])) }}" class="markdown-list-item {{ $selectedId === $document->id ? 'is-selected' : '' }}" data-document-id="{{ $document->id }}"
                wire:key="document-result-{{ $document->id }}" @click="closeListForSelection()" @if ($selectedId === $document->id) aria-current="page" @endif>
                <span class="markdown-list-title"><span data-document-list-title>{{ $document->display_title }}</span>@if ($document->is_pinned)<x-icon name="pin-filled" aria-label="Pinned" />@endif</span>
                <span class="markdown-list-tags" data-document-list-tags @if ($document->tags->isEmpty()) hidden @endif>{{ $document->tags->map(fn ($tag) => '#'.$tag->name)->implode(' ') }}</span>
                <span class="markdown-list-preview" data-document-list-preview>{{ filled($document->content_text) ? Str::limit($document->content_text, 58) : 'Empty document' }}</span>
                <time data-document-list-updated datetime="{{ $document->updated_at->toAtomString() }}">{{ $document->updated_at->diffForHumans() }}</time>
            </a>
        @empty
            <div class="markdown-list-empty">
                <x-icon name="markdown" />
                <p>{{ $search !== '' ? 'No documents match this search.' : 'No documents yet.' }}</p>
                @if ($search !== '')<button type="button" wire:click="$set('search', '')">Clear search</button>@endif
            </div>
        @endforelse
    </div>

    @if ($documents->previousPageUrl() || $documents->nextPageUrl())
        <nav class="markdown-pagination" aria-label="Document pages">
            @if ($documents->previousPageUrl())<a href="{{ $documents->previousPageUrl() }}" wire:click.prevent="previousPage">Previous</a>@else<span>Previous</span>@endif
            @if ($documents->nextPageUrl())<a href="{{ $documents->nextPageUrl() }}" wire:click.prevent="nextPage">Next</a>@else<span>Next</span>@endif
        </nav>
    @endif
</div>
