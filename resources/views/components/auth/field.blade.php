@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

<div>
    <div class="mb-1.5 flex items-start justify-between gap-2">
        <label for="{{ $name }}" class="text-[11px] font-medium leading-relaxed text-muted">{{ $label }}</label>
        @isset($labelAction){{ $labelAction }}@endisset
    </div>

    <div @if ($type === 'password') x-data="{ visible: false }" @endif class="relative">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($type !== 'password') value="{{ is_scalar(old($name, $value)) ? old($name, $value) : '' }}" @endif
            @if ($type === 'password') :type="visible ? 'text' : 'password'" @endif
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            @if ($errors->has($name)) aria-describedby="{{ $name }}-error"
            @elseif ($hint) aria-describedby="{{ $name }}-hint" @endif
            {{ $attributes->class(['auth-input', 'pr-11' => $type === 'password']) }}
        >

        @if ($type === 'password')
            <button type="button" x-cloak @click="visible = !visible" :aria-pressed="visible"
                aria-label="Show {{ strtolower($label) }}" :aria-label="visible ? 'Hide {{ strtolower($label) }}' : 'Show {{ strtolower($label) }}'"
                class="absolute inset-y-0 right-0 grid w-11 cursor-pointer place-items-center rounded-r-lg text-muted transition-colors hover:text-ink focus-visible:z-10">
                <x-icon name="eye" x-show="!visible" class="text-base" />
                <x-icon name="eye-off" x-show="visible" x-cloak class="text-base" />
            </button>
        @endif
    </div>

    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1.5 text-[10px] leading-relaxed text-muted">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-[11px] leading-relaxed text-rose-300">{{ $message }}</p>
    @enderror
</div>
