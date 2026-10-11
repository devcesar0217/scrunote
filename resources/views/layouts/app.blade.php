<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        @yield('title', 'Scrunote')
    </title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="h-full bg-canvas font-sans text-ink antialiased">

    <div
        x-data="{ sidebarOpen: false }"
        @keydown.escape.window="sidebarOpen = false"
        class="min-h-full"
    >
        {{-- Mobile backdrop — sits under the sidebar (z-50), above the page --}}
        <div
            x-show="sidebarOpen"
            x-cloak
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Sidebar (fixed, 16rem) --}}
        @include('components.sidebar')

        {{-- Everything else is offset by the sidebar width on ≥ lg --}}
        <div class="flex min-h-screen flex-col lg:pl-64">

            @include('components.navbar')

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>

    @livewireScripts
</body>

</html>