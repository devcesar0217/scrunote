<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Scrunote')</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="h-full bg-canvas font-sans text-ink antialiased">
    <div class="auth-grid relative flex min-h-full items-center justify-center px-4 py-12 sm:px-6">
        @yield('content')
    </div>

    @livewireScripts
</body>

</html>