@props(['eyebrow' => 'YOUR PRIVATE WORKSPACE', 'title', 'description'])

<section aria-labelledby="auth-heading" class="auth-card w-full px-6 py-7 sm:px-7">
    <header class="text-center">
        <div class="mx-auto grid size-11 place-items-center rounded-xl border border-line-strong bg-canvas">
            <img src="{{ asset('images/logo_icon.png') }}" alt="Scrunote" width="30" height="30" class="size-[30px] object-contain">
        </div>
        <p class="mt-4 font-mono text-[9px] font-medium tracking-[0.18em] text-brand-400">{{ $eyebrow }}</p>
        <h1 id="auth-heading" class="mt-2 text-xl font-bold tracking-[-0.035em] text-ink">{{ $title }}</h1>
        <p class="mt-2 text-[11px] leading-relaxed text-muted">{{ $description }}</p>
    </header>

    <div class="mt-6">
        @if (session('status'))
            <div role="status" class="mb-5 rounded-lg border border-brand-500/25 bg-brand-500/8 px-3 py-2.5 text-[11px] leading-relaxed text-brand-200">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" tabindex="-1" id="validation-summary" x-data x-init="$nextTick(() => $el.focus())" class="mb-5 rounded-lg border border-rose-400/25 bg-rose-400/5 px-3 py-2.5 text-[11px] leading-relaxed text-rose-200">
                Please check the highlighted fields below.
            </div>
        @endif

        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="mt-5 border-t border-line-strong/70 pt-4 text-center text-[11px] leading-relaxed text-muted">
            {{ $footer }}
        </footer>
    @endisset
</section>
