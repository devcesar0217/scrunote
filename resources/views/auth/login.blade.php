@extends('layouts.guest')
@section('title', 'Sign in · Scrunote')

@section('content')
    <x-auth.card eyebrow="YOUR SCRUNOTE ACCOUNT" title="Welcome back" description="Sign in to your private developer workspace.">
        <form method="POST" action="{{ route('login.store') }}" class="space-y-3"
            x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault() } else { submitting = true }" @pageshow.window="submitting = false" :aria-busy="submitting">
            @csrf

            <x-auth.field name="login" label="Username or email" autocomplete="username" placeholder="name@example.com" maxlength="254" required autofocus />

            <x-auth.field name="password" label="Password" type="password" autocomplete="current-password" placeholder="Enter your password" maxlength="1024" required />

            <label class="flex w-fit cursor-pointer items-center gap-2.5 py-1 text-[11px] text-muted">
                <input type="checkbox" name="remember" value="1" class="check" @checked(old('remember'))>
                Keep me signed in
            </label>

            <x-auth.submit busy-label="Signing in…">Sign in</x-auth.submit>
        </form>

        @if (config('auth.registration_enabled'))
            <x-slot:footer>
                New to Scrunote?
                <a href="{{ route('register') }}" class="auth-link ml-0.5">Create account</a>
            </x-slot:footer>
        @endif
    </x-auth.card>
@endsection
