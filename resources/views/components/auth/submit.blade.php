@props(['busyLabel' => 'Please wait…'])

<x-button type="submit" class="auth-submit min-h-11 w-full text-xs sm:min-h-10" x-bind:disabled="submitting">
    <x-icon name="loader-2" x-show="submitting" x-cloak class="animate-spin text-base motion-reduce:animate-none" />
    <span x-show="!submitting">{{ $slot }}</span>
    <span x-show="submitting" x-cloak>{{ $busyLabel }}</span>
</x-button>
