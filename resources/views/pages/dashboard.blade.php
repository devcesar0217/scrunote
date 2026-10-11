@extends('layouts.app')

@section('title', 'Overview · Scrunote')
@section('page-title', 'Overview')

@php
    $hour = now()->hour;
    $greeting = match (true) {
        $hour < 12 => 'Good morning',
        $hour < 18 => 'Good afternoon',
        default => 'Good evening',
    };
    $name = auth()->user()->name ?? 'Alex';

    // In a real app these come from the controller / Livewire component.
    $recentWork = [
        ['title' => 'api-auth-middleware.md', 'type' => 'Docs', 'desc' => 'Zero-trust header parsing with asymmetric ECDSA JWT verification fallback', 'time' => '12m ago'],
        ['title' => 'Postgres Connection Pooling', 'type' => 'Note', 'desc' => 'Optimizing PgBouncer pool limits and Prisma timeouts in serverless Lambdas', 'time' => '1h ago'],
        ['title' => 'AWS ECS Deploy Pipeline', 'type' => 'Checklist', 'desc' => 'Task definitions revision matrix and parameter store KMS key triggers', 'time' => '3h ago'],
        ['title' => 'Redis Cluster Caching Strategies', 'type' => 'Note', 'desc' => 'Preventing cache stampedes with probabilistic early expiration algorithms', 'time' => 'Yesterday'],
    ];
@endphp

@section('content')
    {{-- ── Page header ──────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                {{ $greeting }}, {{ $name }}
            </h1>

            <p class="mt-1.5 text-13 text-muted">
                All systems encrypted and in sync.
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <x-button icon="plus">New Note</x-button>

            <x-button icon="file-plus" variant="secondary">
                Doc
            </x-button>

            <x-button icon="lock" variant="secondary">
                Vault
            </x-button>
       
        </div>
    </div>

    {{-- ── Stat cards ───────────────────────────────────────────────── --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-stat-card
            label="Vault Status"
            value="AES-256"
            accent="Active"
            meta="Argon2id · Zero-Knowledge"
        />

        <x-stat-card
            label="Credentials"
            value="14 Stored Keys"
            meta="0 compromised · Auto-lock in 15m"
        />

        <x-stat-card
            label="Storage &amp; Sync"
            value="Local Offline"
            meta="18 documents · Last synced 2m ago"
        />
    </div>

    {{-- ── Recent work ──────────────────────────────────────────────── --}}
    <section class="mt-8">
        <div class="flex items-center justify-between pb-3">
            <h2 class="text-13 font-semibold text-ink">Recent Work</h2>

            <a href="#" class="text-2xs text-faint transition-colors hover:text-muted">
                {{ count($recentWork) }} entries
            </a>
        </div>

        <div class="divide-y divide-line-soft overflow-hidden rounded-card border border-line bg-panel">
            @foreach ($recentWork as $item)
                <a href="#" class="flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-[#141821]">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-13 font-semibold text-ink">
                                {{ $item['title'] }}
                            </span>

                            <x-badge>{{ $item['type'] }}</x-badge>
                        </div>

                        <p class="mt-1 truncate text-xs text-muted">
                            {{ $item['desc'] }}
                        </p>
                    </div>

                    <span class="shrink-0 text-2xs text-faint sm:text-xs">
                        {{ $item['time'] }}
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endsection