{{-- Top bar — sticky, sits to the right of the fixed sidebar --}}
<header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-line
               bg-rail/95 px-4 backdrop-blur sm:px-6">

    {{-- Mobile: open/close the drawer --}}
    <button
        type="button"
        @click="sidebarOpen = !sidebarOpen"
        :aria-expanded="sidebarOpen"
        class="-ml-1 grid size-9 shrink-0 place-items-center rounded-lg text-muted transition-colors hover:bg-raised hover:text-ink lg:hidden"
        aria-label="Toggle navigation"
    >
        <x-icon name="menu-2" class="text-lg" />
    </button>

    {{-- Mobile page title (the desktop layout puts the heading in the page itself) --}}
    <h1 class="truncate text-sm font-semibold text-ink lg:hidden">
        @yield('page-title', 'Overview')
    </h1>

    {{-- Search --}}
    <div class="relative hidden w-full max-w-md lg:block">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />

        <input
            type="search"
            placeholder="Search notes, vault, documents…"
            class="input pl-9 pr-16"
            aria-label="Search"
        >

        <kbd class="kbd pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2">⌘K</kbd>
    </div>

    <div class="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-3">

        {{-- Notifications --}}
        <button
            type="button"
            class="relative grid size-9 place-items-center rounded-lg text-muted transition-colors hover:bg-raised hover:text-ink"
            aria-label="Notifications"
        >
            <x-icon name="bell" class="text-lg" />
            <span class="absolute right-2 top-2 size-1.5 rounded-full bg-brand-400 ring-2 ring-rail"></span>
        </button>

        <span class="hidden h-6 w-px bg-line sm:block"></span>

        {{-- Account menu --}}
        <div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative">
            <button
                type="button"
                @click="open = !open"
                :aria-expanded="open"
                class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 transition-colors hover:bg-raised"
            >
                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-500 text-2xs font-semibold text-brand-950">
                    {{ strtoupper(substr(auth()->user()->name ?? 'Alex', 0, 1)) }}
                </span>

                <span class="hidden text-13 font-medium text-ink sm:block">
                    {{ auth()->user()->name ?? 'Alex' }}
                </span>

                <x-icon
                    name="chevron-down"
                    class="size-4 text-faint transition-transform duration-150"
                    x-bind:class="open && 'rotate-180'"
                />
            </button>

            <div
                x-show="open"
                x-cloak
                @click.outside="open = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-line bg-panel p-1 shadow-menu"
            >
                <div class="border-b border-line-soft px-2.5 pb-2.5 pt-2">
                    <p class="truncate text-13 font-medium text-ink">{{ auth()->user()->email ?? 'alex@scrunote.app' }}</p>
                    <p class="text-2xs text-faint">Signed in · Local vault</p>
                </div>

                <a href="#" class="mt-1 flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-13 text-muted transition-colors hover:bg-raised hover:text-ink">
                    <x-icon name="user-circle" class="size-4" />
                    Profile
                </a>

                <a href="#" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-13 text-muted transition-colors hover:bg-raised hover:text-ink">
                    <x-icon name="key" class="size-4" />
                    API keys
                </a>

                <a href="#" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-13 text-muted transition-colors hover:bg-raised hover:text-ink">
                    <x-icon name="settings" class="size-4" />
                    Settings
                </a>

                <form method="POST" action="#">
                    @csrf

                    <button
                        type="submit"
                        class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-13 text-muted transition-colors hover:bg-raised hover:text-ink"
                    >
                        <x-icon name="logout" class="size-4" />
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
