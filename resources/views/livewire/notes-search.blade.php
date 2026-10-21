<div class="notes-live-search">
    <form class="notes-search" method="GET" action="{{ route('notes.index') }}" wire:submit.prevent>
        <label for="notes-search-query">Search notes</label>
        <div class="notes-search-input">
            <x-icon name="search" />
            <input id="notes-search-query" type="search" name="q" wire:model.live.debounce.300ms="search" maxlength="120"
                placeholder="Title, content, or tag" autocomplete="off">
        </div>
        <label class="notes-project-filter" for="notes-project-filter"><x-icon name="folder" /><span>Location</span>
            <select id="notes-project-filter" name="project" wire:model.live="projectFilter">
                <option value="">All workspace</option>
                <option value="mine">My Workspace</option>
                @foreach ($projects as $project)<option value="{{ $project->id }}">{{ $project->title }}</option>@endforeach
            </select>
        </label>
        <noscript><button type="submit" class="sr-only">Apply Notes search</button></noscript>
    </form>

    <div class="notes-list-summary">
        <span>{{ $notes->count() }} {{ Str::plural('note', $notes->count()) }}{{ $search !== '' ? ' found' : '' }}</span>
        @if ($search !== '')<button type="button" wire:click="$set('search', '')">Clear</button>@endif
        <span class="notes-search-progress" wire:loading wire:target="search,projectFilter">Updating…</span>
    </div>

    <div class="notes-list" aria-live="polite" wire:loading.class="is-loading" wire:target="search,projectFilter">
        @forelse ($notes as $note)
            <a href="{{ route('notes.show', array_filter(['note' => $note, 'project' => $projectFilter])) }}" class="notes-list-item {{ $selectedId === $note->id ? 'is-selected' : '' }}" data-note-id="{{ $note->id }}"
                wire:key="note-result-{{ $note->id }}" @click="closeListForSelection()" @if ($selectedId === $note->id) aria-current="page" @endif>
                <span class="notes-list-title"><span data-note-list-title>{{ $note->display_title }}</span>@if ($note->is_pinned)<x-icon name="pin-filled" aria-label="Pinned" />@endif</span>
                <span class="notes-list-tags" data-note-list-tags @if ($note->tags->isEmpty()) hidden @endif>{{ $note->tags->map(fn ($tag) => '#'.$tag->name)->implode(' ') }}</span>
                <span class="notes-list-preview" data-note-list-preview>{{ filled($note->content_text) ? Str::limit($note->content_text, 58) : 'Empty note' }}</span>
                <time data-note-list-updated datetime="{{ $note->updated_at->toAtomString() }}">{{ $note->updated_at->diffForHumans() }}</time>
            </a>
        @empty
            <div class="notes-list-empty">
                <x-icon name="notes" />
                <p>{{ $search !== '' ? 'No notes match this search.' : 'No notes yet.' }}</p>
                @if ($search !== '')<button type="button" wire:click="$set('search', '')">Clear search</button>@endif
            </div>
        @endforelse
    </div>

    @if ($notes->previousPageUrl() || $notes->nextPageUrl())
        <nav class="notes-pagination" aria-label="Notes pages">
            @if ($notes->previousPageUrl())<a href="{{ $notes->previousPageUrl() }}" wire:click.prevent="previousPage">Previous</a>@else<span>Previous</span>@endif
            @if ($notes->nextPageUrl())<a href="{{ $notes->nextPageUrl() }}" wire:click.prevent="nextPage">Next</a>@else<span>Next</span>@endif
        </nav>
    @endif
</div>
