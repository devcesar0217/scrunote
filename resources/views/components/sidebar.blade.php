@props(['navigation', 'currentRoute', 'mobile' => false])

<div class="workspace-rail-content">
    <div class="workspace-brand">
        <a href="{{ route('dashboard') }}" class="workspace-brand-link" aria-label="Scrunote overview">
            <img src="{{ asset('images/logo_icon.png') }}" width="28" height="28" alt="">
            <span>Scrunote</span>
        </a>
        @if ($mobile)
            <button type="button" class="workspace-icon-button workspace-drawer-close" @click="closeNavigation()" aria-label="Close navigation" autofocus>
                <x-icon name="x" />
            </button>
        @else
            <span class="workspace-edition" aria-label="Web edition">Web</span>
        @endif
    </div>

    <nav class="workspace-navigation" aria-label="{{ $mobile ? 'Mobile primary navigation' : 'Primary navigation' }}" @if ($mobile) @click="if ($event.target.closest('a')) closeNavigation(false)" @endif>
        <div class="workspace-navigation-group">
            <p class="workspace-navigation-label">Workspace</p>
            @foreach ($navigation as $item)
                @if ($item['group'] === 'workspace')
                    <x-nav-item :href="$item['url']" :icon="$item['icon']" :active="$currentRoute === $item['route']">{{ $item['label'] }}</x-nav-item>
                @endif
            @endforeach
        </div>
        <div class="workspace-navigation-group">
            <p class="workspace-navigation-label">Modules</p>
            @foreach ($navigation as $item)
                @if ($item['group'] === 'modules')
                    <x-nav-item :href="$item['url']" :icon="$item['icon']" :active="$currentRoute === $item['route']">{{ $item['label'] }}</x-nav-item>
                @endif
            @endforeach
        </div>
    </nav>

    <div class="workspace-rail-footer" @if ($mobile) @click="if ($event.target.closest('a')) closeNavigation(false)" @endif>
        @foreach ($navigation as $item)
            @if ($item['group'] === 'settings')
                <x-nav-item :href="$item['url']" :icon="$item['icon']" :active="$currentRoute === $item['route']">{{ $item['label'] }}</x-nav-item>
            @endif
        @endforeach
    </div>
</div>
