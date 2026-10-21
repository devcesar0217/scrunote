<div class="global-search-live-results">
    <form class="global-search-form" method="GET" action="{{ route('search.index') }}" wire:submit.prevent>
        <label for="global-search-query">Search your private workspace</label>
        <div>
            <x-icon name="search" />
            <input id="global-search-query" type="search" name="q" wire:model.live.debounce.300ms="query" maxlength="120" minlength="{{ $minimumQueryLength }}"
                placeholder="Try a project name, tag, title, or metadata" autocomplete="off" autofocus>
            <button type="submit" class="workspace-secondary-button">Search</button>
        </div>
        <p>Vault secret values and encrypted notes are never searched.</p>
    </form>

    <p class="global-search-live-status" role="status" aria-live="polite" wire:loading wire:target="query">Searching your private workspace…</p>

    @if ($query === '')
        <section class="global-search-empty workspace-section-panel" aria-label="Search guidance">
            <div class="workspace-section-empty">
                <span class="workspace-section-empty-icon"><x-icon name="search" /></span>
                <h2>Find information across your workspace</h2>
                <p>Search Projects, saved Notes, Markdown Docs, tags, and safe Vault metadata. Use Ctrl/⌘ K anywhere to open the quick search dialog.</p>
            </div>
        </section>
    @elseif (mb_strlen($query) < $minimumQueryLength)
        <p class="global-search-message" role="status">Type at least {{ $minimumQueryLength }} characters to search your workspace.</p>
    @elseif ($results === [])
        <p class="global-search-message" role="status">No private workspace items match “{{ $query }}”.</p>
    @else
        <section class="global-search-results" aria-labelledby="global-search-results-heading" wire:loading.class="is-loading" wire:target="query">
            <header>
                <h2 id="global-search-results-heading">{{ count($results) }} {{ Str::plural('result', count($results)) }}</h2>
                @if ($hasMore)<p>More matches may be available; refine the search to narrow it down.</p>@endif
            </header>
            <ul>
                @foreach ($results as $result)
                    <li wire:key="global-search-result-{{ $result['id'] }}">
                        <a href="{{ $result['url'] }}" data-module="{{ $result['module'] }}">
                            <span class="global-search-result-icon"><x-icon :name="$result['icon']" /></span>
                            <span class="global-search-result-copy">
                                <strong>{{ $result['title'] }}</strong>
                                <span>{{ $result['snippet'] }}</span>
                            </span>
                            <span class="global-search-result-label">{{ $result['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
