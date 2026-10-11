@props([
    'icon' => null,
    'href' => '#',
    'active' => false,
])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class(['nav-item', 'nav-item-active' => $active]) }}
>
    @if ($icon)
        <x-icon :name="$icon" class="nav-item-icon text-lg" />
    @endif

    <span class="truncate">{{ $slot }}</span>
</a>
