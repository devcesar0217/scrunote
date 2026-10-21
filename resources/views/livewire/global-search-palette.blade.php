<div class="workspace-palette-live-root">
    <form class="workspace-palette-search" method="GET" action="{{ route('search.index') }}" wire:submit.prevent>
        <x-icon name="search" />
        <label for="workspace-search-query" class="sr-only">Search Projects, Notes, Markdown Docs, and Vault metadata</label>
        <input id="workspace-search-query" type="search" name="q" wire:model.live.debounce.220ms="query" maxlength="120"
            placeholder="Search your workspace…" autocomplete="off" autofocus @keydown.enter.prevent="visitFirstPaletteResult()">
        <button type="button" class="workspace-icon-button" @click="closePalette()" aria-label="Close global search">
            <x-icon name="x" />
        </button>
        <noscript><button type="submit" class="sr-only">Search</button></noscript>
    </form>
    <div class="workspace-palette-context">
        @if ($query === '')<span>Global Search</span><span>Projects · Notes · Markdown · Vault</span>
        @elseif (mb_strlen($query) < $minimumQueryLength)<span>Global Search</span><span>Type at least {{ $minimumQueryLength }} characters</span>
        @else <span>{{ $hasMore ? 'Refine your search for more matches' : 'Private workspace results' }}</span><span>{{ count($results) }} {{ Str::plural('result', count($results)) }}</span>@endif
    </div>
    <ul class="workspace-palette-results" aria-label="Global search results" wire:loading.class="is-loading" wire:target="query">
        @foreach ($results as $item)
            <li wire:key="palette-result-{{ $item['id'] }}">
                <a href="{{ $item['url'] }}" class="workspace-palette-result" data-module="{{ $item['module'] }}" @click="closePalette(false)">
                    <span class="workspace-palette-icon"><x-icon :name="$item['icon']" /></span>
                    <span class="workspace-palette-result-copy">
                        <strong>{{ $item['title'] }}</strong>
                        <span>{{ $item['snippet'] }}</span>
                    </span>
                    <span class="workspace-palette-phase">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    @if ($query !== '' && mb_strlen($query) >= $minimumQueryLength && $results === [])
        <p class="workspace-palette-empty">No private workspace items match this search.</p>
    @endif
    <p class="workspace-palette-empty" wire:loading wire:target="query">Searching…</p>
    <div class="workspace-palette-footer">
        <p>Searches your Projects, Notes, Markdown Docs, tags, and Vault non-secret metadata. Vault secrets and encrypted notes are never searched.</p>
        <span><kbd>↑</kbd><kbd>↓</kbd> navigate <kbd>Enter</kbd> open <kbd>Esc</kbd> close</span>
    </div>
</div>
