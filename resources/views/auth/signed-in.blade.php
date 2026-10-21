@extends('layouts.guest')
@section('title', 'Signed in · Scrunote')

@section('content')
    <x-auth.card eyebrow="AUTHENTICATION CHECKPOINT" title="You’re signed in" description="Your Scrunote account is ready.">
        <dl class="space-y-4 rounded-lg border border-line-strong bg-canvas px-4 py-4 text-xs">
            <div>
                <dt class="text-[10px] text-muted">Name</dt>
                <dd class="mt-1 break-words font-semibold">{{ auth()->user()->name }}</dd>
            </div>
            <div>
                <dt class="text-[10px] text-muted">Username</dt>
                <dd class="mt-1 break-words">{{ auth()->user()->username }}</dd>
            </div>
            <div>
                <dt class="text-[10px] text-muted">Email address</dt>
                <dd class="mt-1 break-words">{{ auth()->user()->email }}</dd>
            </div>
        </dl>
        <p class="my-5 text-[11px] leading-relaxed text-muted">
            Phase 2 covers your account and authentication. The shared workspace, Notes, Markdown, Vault and AI features will follow in their approved phases.
        </p>
        <form method="POST" action="{{ route('logout') }}" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault() } else { submitting = true }" @pageshow.window="submitting = false" :aria-busy="submitting">
            @csrf
            <x-auth.submit busy-label="Signing out…">Sign out</x-auth.submit>
        </form>
    </x-auth.card>
@endsection
