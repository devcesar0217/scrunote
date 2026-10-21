<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="min-h-full">
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
<body class="auth-page min-h-svh">
    <main id="main-content" class="auth-background flex min-h-svh items-center justify-center px-4 py-6 sm:px-6 sm:py-8">
        <div class="w-full max-w-[25rem]">
            @yield('content')
            <footer class="mt-5 space-y-2.5 text-center text-[10px] leading-relaxed text-muted">
                <p class="flex items-center justify-center gap-2">
                    <span class="size-1.5 rounded-full bg-brand-500" aria-hidden="true"></span>
                    Private account · Server-side session
                </p>
                <p>Scrunote · Web edition</p>
            </footer>
        </div>
    </main>
    @livewireScripts
</body>
</html>
