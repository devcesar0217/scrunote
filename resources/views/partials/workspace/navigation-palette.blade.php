<dialog id="workspace-palette" class="workspace-palette" x-ref="palette" aria-labelledby="workspace-palette-title"
    @keydown.tab="cycleDialogFocus($event, 'palette')" @close="paletteClosed()" @click="dismissBackdrop($event, 'palette')" @keydown.arrow-down.prevent="moveResult(1)" @keydown.arrow-up.prevent="moveResult(-1)">
    <h2 id="workspace-palette-title" class="sr-only">Global Search</h2>
    <div class="workspace-palette-search">
        <x-icon name="search" />
        <label for="workspace-search-query" class="sr-only">Search Notes, Markdown Docs, and Vault metadata</label>
        <input id="workspace-search-query" type="search" x-ref="paletteInput" x-model="searchQuery" maxlength="120"
            placeholder="Search your workspace…" autocomplete="off" autofocus @input="queueSearch()" @keydown.enter.prevent="visitFirstResult()">
        <button type="button" class="workspace-icon-button" @click="closePalette()" aria-label="Close global search">
            <x-icon name="x" />
        </button>
    </div>
    <div class="workspace-palette-context">
        <span x-text="searchContext"></span>
        <span x-text="searchCount" role="status" aria-live="polite"></span>
    </div>
    <ul class="workspace-palette-results" x-ref="paletteResults" aria-label="Global search results">
        <template x-for="item in searchResults" :key="item.id">
            <li>
                <a :href="item.url" class="workspace-palette-result" :data-module="item.module" @click="closePalette(false)">
                    <span class="workspace-palette-icon"><i :class="'ti ti-' + item.icon" aria-hidden="true"></i></span>
                    <span class="workspace-palette-result-copy">
                        <strong x-text="item.title"></strong>
                        <span x-text="item.snippet"></span>
                    </span>
                    <span class="workspace-palette-phase" x-text="item.label"></span>
                </a>
            </li>
        </template>
    </ul>
    <p class="workspace-palette-empty" x-show="searchMessage !== ''" x-text="searchMessage" x-cloak></p>
    <div class="workspace-palette-footer">
        <p>Searches your Notes, Markdown Docs, tags, and Vault non-secret metadata. Vault secrets and encrypted notes are never searched.</p>
        <span><kbd>↑</kbd><kbd>↓</kbd> navigate <kbd>Enter</kbd> open <kbd>Esc</kbd> close</span>
    </div>
</dialog>
