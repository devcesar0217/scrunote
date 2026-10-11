@props(['tone' => 'default'])

@php
    $tones = [
        'default' => 'border-transparent bg-raised text-muted',
        'brand' => 'border-brand-500/25 bg-brand-500/10 text-brand-400',
        'info' => 'border-sky-500/25 bg-sky-500/10 text-sky-300',
        'warn' => 'border-amber-500/25 bg-amber-500/10 text-amber-300',
        'danger' => 'border-rose-500/25 bg-rose-500/10 text-rose-300',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-md border px-2 py-0.5 text-2xs font-medium',
    $tones[$tone] ?? $tones['default'],
]) }}>
    {{ $slot }}
</span>
