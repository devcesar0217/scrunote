@props(['label', 'value', 'accent' => null, 'meta' => null])

{{--
    Stat card — e.g.
    <x-stat-card label="Vault Status" value="AES-256" accent="Active" meta="Argon2id · Zero-Knowledge" />
--}}

<div {{ $attributes->class(['card p-5']) }}>
    <p class="text-13 font-medium text-muted">{{ $label }}</p>

    <div class="mt-2 flex items-center gap-1.5">
        <span class="text-[17px] font-semibold tracking-tight text-ink">{{ $value }}</span>

        @if ($accent)
            <span class="inline-flex items-center text-13 font-medium text-brand-400">
                {{ $accent }}
                <x-icon name="arrow-up-right" class="size-3.5" />
            </span>
        @endif
    </div>

    @if ($meta)
        <p class="mt-2 text-xs text-faint">{{ $meta }}</p>
    @endif
</div>
