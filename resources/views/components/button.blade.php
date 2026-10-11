@props([
    'icon' => null,
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'ghost' => 'btn-ghost',
    ];

    $classes = $variants[$variant] ?? $variants['primary'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes, 'cursor-pointer']) }}>
        @if ($icon)
            <x-icon :name="$icon" class="text-lg" />
        @endif

        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes, 'cursor-pointer']) }}>
        @if ($icon)
            <x-icon :name="$icon" class="text-lg" />
        @endif

        {{ $slot }}
    </button>
@endif