<div class="vault-live-search">
    <form class="vault-search" method="GET" action="{{ route('vault.index') }}" wire:submit.prevent>
        <label for="vault-search-query">Search Vault</label>
        <div class="vault-search-input"><x-icon name="search" /><input id="vault-search-query" type="search" name="q" wire:model.live.debounce.300ms="search" maxlength="120" placeholder="Name, URL, username, tag" autocomplete="off"></div>
        <label class="vault-project-filter" for="vault-project-filter"><x-icon name="folder" /><span>Location</span>
            <select id="vault-project-filter" name="project" wire:model.live="projectFilter">
                <option value="">All workspace</option>
                <option value="mine">My Workspace</option>
                @foreach ($projects as $project)<option value="{{ $project->id }}">{{ $project->title }}</option>@endforeach
            </select>
        </label>
        <noscript><button type="submit" class="sr-only">Apply Vault search</button></noscript>
    </form>

    @if ($vault)
        <div class="vault-list-summary">
            <span>{{ $entries->count() }} {{ Str::plural('entry', $entries->count()) }}{{ $search !== '' ? ' found' : '' }}</span>
            @if ($search !== '')<button type="button" wire:click="$set('search', '')">Clear</button>@endif
            <span class="vault-search-progress" wire:loading wire:target="search,projectFilter">Updating…</span>
        </div>
        <div class="vault-list" aria-live="polite" wire:loading.class="is-loading" wire:target="search,projectFilter">
            @forelse ($entries as $entry)
                <a href="{{ route('vault.show', array_filter(['vaultEntry' => $entry, 'project' => $projectFilter])) }}" class="vault-list-item {{ $selectedId === $entry->id ? 'is-selected' : '' }}" wire:key="vault-result-{{ $entry->id }}" @if ($selectedId === $entry->id) aria-current="page" @endif>
                    <span class="vault-list-title">{{ $entry->title }} @if ($entry->is_pinned)<x-icon name="pin-filled" aria-label="Pinned" />@endif</span>
                    @if ($entry->username)<span class="vault-list-user">{{ $entry->username }}</span>@endif
                    @if ($entry->tags->isNotEmpty())<span class="vault-list-tags">{{ $entry->tags->map(fn ($tag) => '#'.$tag->name)->implode(' ') }}</span>@endif
                    <time datetime="{{ $entry->updated_at->toAtomString() }}">{{ $entry->updated_at->diffForHumans() }}</time>
                </a>
            @empty
                <div class="vault-list-empty"><x-icon name="shield-lock" /><p>{{ $search !== '' ? 'No entries match this search.' : 'No Vault entries yet.' }}</p>@if ($search !== '')<button type="button" wire:click="$set('search', '')">Clear search</button>@endif</div>
            @endforelse
        </div>
        @if ($entries->previousPageUrl() || $entries->nextPageUrl())
            <nav class="vault-pagination" aria-label="Vault entry pages">
                @if ($entries->previousPageUrl())<a href="{{ $entries->previousPageUrl() }}" wire:click.prevent="previousPage">Previous</a>@else<span>Previous</span>@endif
                @if ($entries->nextPageUrl())<a href="{{ $entries->nextPageUrl() }}" wire:click.prevent="nextPage">Next</a>@else<span>Next</span>@endif
            </nav>
        @endif
    @else
        <div class="vault-list-empty"><x-icon name="lock" /><p>Set up your Vault to begin storing encrypted credentials.</p></div>
    @endif
</div>
