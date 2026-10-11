@extends('layouts.guest')

@section('title', 'Sign in · Scrunote')

@section('content')
    <div class="auth-card w-full max-w-110 px-8 py-10 sm:px-10">

        {{-- Mark + heading --}}
        <div class="flex flex-col items-center text-center">
            <img
                src="{{ asset('images/logo_icon.png') }}"
                alt="{{ config('app.name') }} Logo" class="h-10 w-auto"
            />

            <h1 class="mt-5 text-[22px] font-semibold tracking-tight text-ink">
                Welcome back
            </h1>

            <p class="mt-1.5 text-13 text-muted">
                Enter your credentials to access your account.
            </p>
        </div>

        @if (session('status'))
            <p class="mt-6 rounded-lg border border-brand-500/20 bg-brand-500/10 px-3 py-2 text-center text-13 text-brand-400">
                {{ session('status') }}
            </p>
        @endif

        <form method="POST" action="{{ Route::has('login') ? route('login') : url('/login') }}" class="mt-8 space-y-4">
            @csrf

            {{-- Username / email --}}
            <div>
                <label for="email" class="mb-1.5 block text-13 font-medium text-ink">
                    Username or Email
                </label>

                <div class="relative hidden w-full max-w-md lg:block">
                    <x-icon name="user" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
            
                    <x-input
                        id="email"
                        name="email"
                        type="text"
                        :value="old('email')"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="name123/name@example.com"
                        :input-class="'h-10 bg-canvas pl-9 ' . ($errors->has('email') ? 'border-rose-500/60' : '')"
                    />
            
                    @error('email')
                        <p class="mt-1.5 text-2xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Password + forgot --}}
            <div>
                <div class="mb-1.5 flex items-center justify-between gap-3">
                    <label for="password" class="text-13 font-medium text-ink">Password</label>

                    {{-- <a href="#" class="text-13 text-muted transition-colors hover:text-ink">
                        Forgot password?
                    </a> --}}
                </div>

                <div
                    x-data="{ show: false }"
                    class="relative hidden w-full max-w-md lg:block"
                >
                    <x-icon name="lock" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
                    <input
                        id="password"
                        name="password"
                        :type="show ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        placeholder="********"
                        class="input h-10 bg-canvas pl-9 pr-10 @error('password') border-rose-500/60 @enderror"
                    >

                    <button
                        type="button"
                        @click="show = !show"
                        class="absolute inset-y-0 right-0 grid w-10 place-items-center text-faint transition-colors hover:text-muted"
                        :aria-label="show ? 'Hide password' : 'Show password'"
                    >
                        <x-icon name="eye" class="size-4" x-show="!show" />
                        <x-icon name="eye-off" class="size-4" x-show="show" x-cloak />
                    </button>
                </div>

                @error('password')
                    <p class="mt-1.5 text-2xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember --}}
            <label class="flex cursor-pointer items-center gap-2.5 select-none">
                <input
                    id="remember"
                    type="checkbox"
                    name="remember"
                    class="check"
                    {{ old('remember') ? 'checked' : '' }}
                >
                <span class="text-13 text-muted">Remember me</span>
            </label>

            <x-button type="submit" variant="primary" class="mt-1 w-full text-sm">
                Sign in
            </x-button>
       
        </form>

        {{-- Divider --}}
        <div class="mt-8 flex items-center gap-3">
            <span class="h-px flex-1 bg-line"></span>
            <span class="text-13 text-faint">or</span>
            <span class="h-px flex-1 bg-line"></span>
        </div>

        <p class="mt-6 text-center text-13 text-muted">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-medium text-brand-400 transition-colors hover:text-brand-300">Sign up</a>
        </p>
    </div>
@endsection