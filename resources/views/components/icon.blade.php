@props([
    'name',
    'stroke' => 0,
])

@php
    $iconName = str_replace('_', '-', $name);
    $iconClass = "ti ti-{$iconName}";
@endphp

<i {{ $attributes->merge(['class' => $iconClass]) }} aria-hidden="true"></i>
