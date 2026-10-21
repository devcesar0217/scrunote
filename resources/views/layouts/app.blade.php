<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Scrunote')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_icon.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="workspace-page">
    <a class="workspace-skip" href="#workspace-content">Skip to content</a>

    <div class="workspace-shell" x-data="workspaceShell({{ Illuminate\Support\Js::from($navigation) }})"
        @keydown.window="handleShortcut($event)" @resize.window.debounce.120ms="handleResize()"
        @pageshow.window="restorePageState()">
        <aside class="workspace-desktop-rail" aria-label="Workspace sidebar">
            <x-sidebar :navigation="$navigation" :current-route="$page['route']" />
        </aside>

        <div class="workspace-body">
            <x-navbar :page="$page" :identity="$identity" />

            <noscript>
                <nav class="workspace-noscript-nav" aria-label="Workspace navigation without JavaScript">
                    @foreach ($navigation as $item)
                        <a href="{{ $item['url'] }}" @if ($page['route'] === $item['route']) aria-current="page" @endif>{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            </noscript>

            <main id="workspace-content" tabindex="-1" class="workspace-main" data-section="{{ $page['id'] }}">
                @yield('content')
            </main>
        </div>

        <dialog class="workspace-navigation-dialog" id="workspace-navigation" x-ref="navigation"
            aria-label="Workspace navigation" @keydown.tab="cycleDialogFocus($event, 'navigation')" @close="navigationClosed()" @click="dismissBackdrop($event, 'navigation')">
            <x-sidebar :navigation="$navigation" :current-route="$page['route']" mobile />
        </dialog>

        @include('partials.workspace.navigation-palette')
    </div>

    @livewireScripts
</body>
</html>
