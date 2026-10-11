@props([
    'type' => 'text',
    'name' => null,
    'id' => null,
    'value' => null,
    'placeholder' => '',
    'disabled' => false,
    'required' => false,
    'autocomplete' => null,
    'autofocus' => false,
    'class' => '',
    'inputClass' => '',
])

<input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $id ?? $name }}"
    value="{{ old($name, $value) }}"
    {{ $placeholder ? "placeholder=$placeholder" : '' }}
    {{ $disabled ? 'disabled' : '' }}
    {{ $required ? 'required' : '' }}
    {{ $autocomplete ? "autocomplete=$autocomplete" : '' }}
    {{ $autofocus ? 'autofocus' : '' }}
    {{ $attributes->merge(['class' => trim("input {$inputClass} {$class}")]) }}
/>