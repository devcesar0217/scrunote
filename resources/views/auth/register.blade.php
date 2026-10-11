@extends('layouts.guest')

@section('title', 'Sign up · Scrunote')

@section('content')
    <div class="auth-card w-full max-w-150 px-8 py-10 sm:px-10">

        {{-- Mark + heading --}}
        <div class="flex flex-col items-center text-center">
            <img
                src="{{ asset('images/logo_icon.png') }}"
                alt="{{ config('app.name') }} Logo" class="h-10 w-auto"
            />

            <h1 class="mt-5 text-[22px] font-semibold tracking-tight text-ink">
                Create your account
            </h1>

            <p class="mt-1.5 text-13 text-muted">
                Enter your details to get started.
            </p>
        </div>

        @if (session('status'))
            <p class="mt-6 rounded-lg border border-brand-500/20 bg-brand-500/10 px-3 py-2 text-center text-13 text-brand-400">
                {{ session('status') }}
            </p>
        @endif

        <form method="POST" action="{{ Route::has('login') ? route('login') : url('/login') }}" class="mt-8 space-y-4">
            @csrf

            {{-- First Name and Last Name --}}
            <div class="flex flex-col gap-4 md:flex-row md:gap-7">
                <div class="flex-1">
                    <label for="first_name" class="mb-1.5 block text-13 font-medium text-ink">
                        First Name
                    </label>
                    <div class="relative w-full">
                        <x-input
                            id="first_name"
                            name="first_name"
                            type="text"
                            :value="old('first_name')"
                            required
                            autocomplete="given-name"
                            placeholder="First name"
                            :input-class="'h-10 bg-canvas px-3 ' . ($errors->has('first_name') ? 'border-rose-500/60' : '')"
                        />
                        @error('first_name')
                            <p class="mt-1.5 text-2xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="flex-1">
                    <label for="last_name" class="mb-1.5 block text-13 font-medium text-ink">
                        Last Name
                    </label>
                    <div class="relative w-full">
                        <x-input
                            id="last_name"
                            name="last_name"
                            type="text"
                            :value="old('last_name')"
                            required
                            autocomplete="family-name"
                            placeholder="Last name"
                            :input-class="'h-10 bg-canvas px-3 ' . ($errors->has('last_name') ? 'border-rose-500/60' : '')"
                        />
                        @error('last_name')
                            <p class="mt-1.5 text-2xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
     
            {{-- Username --}}
            <div>
                <label for="username" class="mb-1.5 block text-13 font-medium text-ink">
                    Username
                </label>
                <div class="relative w-full">
                    <x-icon name="user" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />

                    <x-input
                        id="username"
                        name="username"
                        type="text"
                        :value="old('username')"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="name123"
                        :input-class="'h-10 bg-canvas pl-9 ' . ($errors->has('username') ? 'border-rose-500/60' : '')"
                    />

                    @error('username')
                        <p class="mt-1.5 text-2xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="mb-1.5 block text-13 font-medium text-ink">
                    Email
                </label>
                <div class="relative w-full">
                    <x-icon name="mail" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />

                    <x-input
                        id="email"
                        name="email"
                        type="email"
                        :value="old('email')"
                        required
                        autocomplete="email"
                        placeholder="name@example.com"
                        :input-class="'h-10 bg-canvas pl-9 ' . ($errors->has('email') ? 'border-rose-500/60' : '')"
                    />

                    @error('email')
                        <p class="mt-1.5 text-2xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
          
            {{-- Password + confirm password (row layout) --}}
            <div class="flex flex-col md:flex-row gap-4">
                {{-- Password --}}
                <div class="flex-1">
                    <label for="password" class="text-13 font-medium text-ink">Password</label>

                    <div
                        x-data="{ show: false }"
                        class="relative w-full"
                    >
                        <x-icon name="lock" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
                        <input
                            id="password"
                            name="password"
                            :type="show ? 'text' : 'password'"
                            required
                            autocomplete="new-password"
                            placeholder="********"
                            class="input h-10 bg-canvas pl-9 pr-10 @error('password') border-rose-500/60 @enderror"
                        >

                        <button
                            type="button"
                            @click="show = !show"
                            class="absolute inset-y-0 right-0 grid w-10 place-items-center text-faint transition-colors hover:text-muted cursor-pointer"
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

                {{-- Confirm Password --}}
                <div class="flex-1">
                    <label for="password_confirmation" class="text-13 font-medium text-ink">Confirm Password</label>

                    <div
                        x-data="{ show_confirm: false }"
                        class="relative w-full"
                    >
                        <x-icon name="lock" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            :type="show_confirm ? 'text' : 'password'"
                            required
                            autocomplete="new-password"
                            placeholder="********"
                            class="input h-10 bg-canvas pl-9 pr-10 @error('password_confirmation') border-rose-500/60 @enderror"
                        >

                        <button
                            type="button"
                            @click="show_confirm = !show_confirm"
                            class="absolute inset-y-0 right-0 grid w-10 place-items-center text-faint transition-colors hover:text-muted cursor-pointer"
                            :aria-label="show_confirm ? 'Hide password' : 'Show password'"
                        >
                            <x-icon name="eye" class="size-4" x-show="!show_confirm" />
                            <x-icon name="eye-off" class="size-4" x-show="show_confirm" x-cloak />
                        </button>
                    </div>
                    
                    @error('password_confirmation')
                        <p class="mt-1.5 text-2xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
              
            {{-- Terms and Conditions --}}
            <label class="flex cursor-pointer items-center gap-2.5 select-none">
                <input
                    id="terms"
                    type="checkbox"
                    name="terms"
                    class="check"
                    {{ old('terms') ? 'checked' : '' }}
                    required
                >
                <span class="text-13 text-muted">
                    I agree to the
                    <a href="#" class="font-medium text-brand-400 hover:text-brand-300 transition-colors">Terms and Conditions</a>
                </span>
            </label>
            @error('terms')
                <p class="mt-1.5 text-2xs text-rose-400">{{ $message }}</p>
            @enderror
       
            <x-button type="submit" variant="primary" class="mt-1 w-full text-sm">
                Sign up
            </x-button>
       
        </form>

        {{-- Divider --}}
        <div class="mt-8 flex items-center gap-3">
            <span class="h-px flex-1 bg-line"></span>
            <span class="text-13 text-faint">or</span>
            <span class="h-px flex-1 bg-line"></span>
        </div>

        <p class="mt-6 text-center text-13 text-muted">
            Already have an account?
            <a href="{{ route('login') }}" class="font-medium text-brand-400 transition-colors hover:text-brand-300">Sign in</a>
        </p>
    </div>
@endsection