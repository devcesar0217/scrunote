<dialog id="workspace-palette" class="workspace-palette" x-ref="palette" aria-labelledby="workspace-palette-title"
    @keydown.tab="cycleDialogFocus($event, 'palette')" @close="paletteClosed()" @click="dismissBackdrop($event, 'palette')" @keydown.arrow-down.prevent="moveResult(1)" @keydown.arrow-up.prevent="moveResult(-1)">
    <h2 id="workspace-palette-title" class="sr-only">Navigate workspace</h2>
    <div class="workspace-palette-search">
        <x-icon name="search" />
        <label for="workspace-section-query" class="sr-only">Find a workspace section</label>
        <input id="workspace-section-query" type="search" x-ref="paletteInput" x-model="query" maxlength="80"
            placeholder="Find a workspace section…" autocomplete="off" autofocus @keydown.enter.prevent="visitFirstResult()">
        <button type="button" class="workspace-icon-button" @click="closePalette()" aria-label="Close workspace navigation">
            <x-icon name="x" />
        </button>
    </div>
    <div class="workspace-palette-context">
        <span>Workspace sections</span>
        <span x-text="matchingSections.length + (matchingSections.length === 1 ? ' result' : ' results')" role="status" aria-live="polite"></span>
    </div>
    <ul class="workspace-palette-results" x-ref="paletteResults" aria-label="Matching workspace sections">
        <template x-for="item in matchingSections" :key="item.id">
            <li>
                <a :href="item.url" class="workspace-palette-result" @click="closePalette(false)">
                    <span class="workspace-palette-icon"><i :class="'ti ti-' + item.icon" aria-hidden="true"></i></span>
                    <span class="workspace-palette-result-copy">
                        <strong x-text="item.label"></strong>
                        <span x-text="item.description"></span>
                    </span>
                    <span class="workspace-palette-phase" x-text="item.id === 'overview' ? 'Shell' : item.phase"></span>
                </a>
            </li>
        </template>
    </ul>
    <p class="workspace-palette-empty" x-show="matchingSections.length === 0" x-cloak>No sections match. Try “Notes” or “Settings”.</p>
    <div class="workspace-palette-footer">
        <p>Navigation only. Content search is not available in Phase 3.</p>
        <span><kbd>↑</kbd><kbd>↓</kbd> navigate <kbd>Esc</kbd> close</span>
    </div>
</dialog>
