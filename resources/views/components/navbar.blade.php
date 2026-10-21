@props(['page', 'identity'])

<header class="workspace-topbar">
    <button type="button" class="workspace-icon-button workspace-mobile-menu" x-cloak
        @click="openNavigation($el)" aria-label="Open navigation" aria-controls="workspace-navigation" :aria-expanded="navigationOpen">
        <x-icon name="menu-2" />
    </button>

    <div class="workspace-breadcrumb" aria-label="Current section">
        <span class="workspace-breadcrumb-root">Workspace</span>
        <span class="workspace-breadcrumb-divider" aria-hidden="true">/</span>
        <span class="workspace-breadcrumb-current" aria-current="page">{{ $page['label'] }}</span>
    </div>

    <button type="button" class="workspace-search-trigger" x-cloak @click="openPalette($el)"
        aria-label="Find a workspace section" aria-haspopup="dialog" aria-controls="workspace-palette">
        <x-icon name="search" />
        <span>Find a workspace section…</span>
        <kbd aria-hidden="true">Ctrl/⌘ K</kbd>
    </button>

    <details class="workspace-account" x-ref="account" @toggle="accountExpanded = $el.open"
        @click.outside="closeAccount(false)" @keydown.escape.stop.prevent="closeAccount()"
        @focusout="if (!$el.contains($event.relatedTarget)) closeAccount(false)">
        <summary class="workspace-account-trigger" aria-label="Account menu for {{ $identity['name'] }}" aria-controls="workspace-account-panel">
            <span class="workspace-avatar" aria-hidden="true">{{ $identity['initial'] }}</span>
            <span class="workspace-account-name">{{ $identity['name'] }}</span>
            <x-icon name="chevron-down" class="workspace-account-chevron" />
        </summary>
        <div class="workspace-account-panel" id="workspace-account-panel">
            <p class="workspace-account-heading">Your account</p>
            <p class="workspace-account-full-name">{{ $identity['name'] }}</p>
            <p class="workspace-account-username">{{ '@'.$identity['username'] }}</p>
            <p class="workspace-account-email">{{ $identity['email'] }}</p>
            <p class="workspace-account-status"><span aria-hidden="true"></span>Signed in · Web account</p>
            <a class="workspace-account-link" href="{{ route('settings.index') }}">
                <x-icon name="settings" /> Settings <x-icon name="chevron-right" class="workspace-account-link-arrow" />
            </a>
            <form method="POST" action="{{ route('logout') }}" x-data="{ submitting: false }"
                @submit="if (submitting) { $event.preventDefault() } else { submitting = true }" @pageshow.window="submitting = false" :aria-busy="submitting">
                @csrf
                <button type="submit" class="workspace-account-logout" :disabled="submitting">
                    <x-icon name="logout" />
                    <span x-show="!submitting">Sign out</span>
                    <span x-show="submitting" x-cloak>Signing out…</span>
                </button>
            </form>
        </div>
    </details>
</header>
