<dialog id="workspace-palette" class="workspace-palette" x-ref="palette" aria-labelledby="workspace-palette-title"
    @keydown.tab="cycleDialogFocus($event, 'palette')" @close="paletteClosed()" @click="dismissBackdrop($event, 'palette')"
    @keydown.arrow-down.prevent="movePaletteResult(1)" @keydown.arrow-up.prevent="movePaletteResult(-1)">
    <h2 id="workspace-palette-title" class="sr-only">Global Search</h2>
    <livewire:global-search-palette />
</dialog>
