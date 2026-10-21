@extends('layouts.app')
@section('title', $page['label'].' · Scrunote')

@section('content')
    <header class="workspace-section-header">
        <div>
            @if ($page['id'] === 'settings')<p class="workspace-eyebrow">Preferences</p>@endif
            <h1>{{ $page['label'] }}</h1>
            @if ($page['id'] !== 'settings')<p class="workspace-section-description">{{ $page['description'] }}</p>@endif
        </div>
        <span class="workspace-status-badge">Planned · {{ $page['phase'] }}</span>
    </header>

    <section class="workspace-section-panel" aria-labelledby="section-unavailable-title" data-module-unavailable="{{ $page['id'] }}">
        <div class="workspace-section-empty">
            <span class="workspace-section-empty-icon"><x-icon :name="$page['icon']" /></span>
            <p class="workspace-empty-phase">{{ $page['phase'] }} · Not implemented yet</p>
            <h2 id="section-unavailable-title">{{ $page['unavailable_title'] }}</h2>
            <p>{{ $page['unavailable_description'] }}</p>
            <a href="{{ route('dashboard') }}" class="workspace-secondary-button"><x-icon name="arrow-left" /> Back to overview</a>
            <p class="workspace-section-boundary">No module records are created, searched or stored in Phase 3.</p>
        </div>
    </section>
@endsection
