@extends('layouts.guest')
@section('title', 'Create account · Scrunote')

@section('content')
    <x-auth.card title="Create your account" description="A private Scrunote workspace, wherever you sign in.">
        <form method="POST" action="{{ route('register.store') }}" class="space-y-3"
            x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault() } else { submitting = true }" @pageshow.window="submitting = false" :aria-busy="submitting">
            @csrf

            <div class="grid gap-3 sm:grid-cols-2">
                <x-auth.field name="first_name" label="First name" autocomplete="given-name" placeholder="Alex" maxlength="100" required autofocus />
                <x-auth.field name="last_name" label="Last name" autocomplete="family-name" placeholder="Morgan" maxlength="100" required />
            </div>

            <x-auth.field name="username" label="Username" autocomplete="username" placeholder="alex.morgan" minlength="3" maxlength="50" autocapitalize="none" spellcheck="false" hint="3–50 characters; letters, numbers, . _ -" required />
            <x-auth.field name="email" label="Email address" type="email" autocomplete="email" placeholder="name@example.com" maxlength="254" required />
            <x-auth.field name="password" label="Password" type="password" autocomplete="new-password" placeholder="At least 12 characters" minlength="12" maxlength="72" hint="12+ characters. Maximum 72 bytes with bcrypt." required />
            <x-auth.field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" placeholder="Repeat your password" minlength="12" maxlength="72" required />

            <x-auth.submit busy-label="Creating account…">Create account</x-auth.submit>
        </form>

        <x-slot:footer>
            Already have an account?
            <a href="{{ route('login') }}" class="auth-link ml-0.5">Sign in</a>
        </x-slot:footer>
    </x-auth.card>
@endsection
