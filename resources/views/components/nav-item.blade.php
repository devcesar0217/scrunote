@props(['icon' => null, 'href', 'active' => false])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif
    {{ $attributes->class(['workspace-nav-item', 'is-active' => $active]) }}>
    @if ($icon)<x-icon :name="$icon" class="workspace-nav-icon" />@endif
    <span>{{ $slot }}</span>
</a>
