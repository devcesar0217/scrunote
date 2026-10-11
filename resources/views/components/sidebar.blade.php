{{--
    Sidebar
    ───────
    `sidebarOpen` comes from the x-data scope on the layout wrapper, so the
    navbar hamburger and the mobile backdrop can toggle it too.

    The slide-in/out is pure CSS (transform + transition):
      • < lg  → off-canvas, driven by `sidebarOpen`
      • ≥ lg  → pinned with `lg:translate-x-0` (which sits *after* the
                 unprefixed -translate-x-full in the stylesheet, so it wins)
--}}
<aside
    data-sidebar
    x-cloak
    @keydown.escape.window="sidebarOpen = false"
    aria-label="Main navigation"
    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-line bg-rail
           transition-transform duration-200 ease-out
           lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    {{-- ── Brand ─────────────────────────────────────────────────────── --}}
    <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-line-soft px-4">
        <img
            src="{{ asset('images/logo_icon.png') }}"
            alt="{{ config('app.name') }} Logo" class="h-8 w-auto"
        />

        <span class="text-[15px] font-semibold tracking-tight text-ink">Scrunote</span>

        <span class="ml-auto rounded-full border border-line bg-raised px-2 py-0.5 text-2xs font-medium text-faint">
            v1.4
        </span>
   
    </div>

    {{-- ── Navigation ──────────────────────────────────────────────────
         @click on the <nav> closes the drawer on mobile after navigating   --}}
    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4" @click="sidebarOpen = false">
        <div>
            <p class="nav-section">Workspace</p>

            <div class="space-y-1">
                <x-nav-item icon="layout-dashboard" :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    Overview
                </x-nav-item>

                <x-nav-item icon="notes" href="#" :active="request()->routeIs('notes*')">
                    Notes
                </x-nav-item>

                <x-nav-item icon="file-text" href="#" :active="request()->routeIs('documents*')">
                    Documents
                </x-nav-item>

                <x-nav-item icon="shield-lock" href="#" :active="request()->routeIs('vault*')">
                    Secure Vault
                </x-nav-item>

                <x-nav-item icon="folder" href="#" :active="request()->routeIs('projects*')">
                    Projects
                </x-nav-item>
            </div>
        </div>

        <div>
            <p class="nav-section">Quick access</p>

            <div class="space-y-1">
                <x-nav-item icon="star" href="#">
                    Starred
                </x-nav-item>

                <x-nav-item icon="archive" href="#">
                    Archive
                </x-nav-item>
            </div>
        </div>
    </nav>

    {{-- ── Footer ──────────────────────────────────────────────────────── --}}
    <div class="shrink-0 border-t border-line-soft p-3">
        <x-nav-item icon="settings" href="#" :active="request()->routeIs('settings*')">
            Settings
        </x-nav-item>
    </div>
</aside>