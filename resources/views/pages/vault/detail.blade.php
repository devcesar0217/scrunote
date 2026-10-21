@php
    $metadata = $selectedEntry->metadata ?? [];
    $typeLabel = match ($selectedEntry->type) {
        'api' => 'API Credential',
        'secure_note' => 'Secure Note',
        default => Str::headline($selectedEntry->type),
    };
@endphp

<section class="vault-entry-detail" x-data="vaultSecret({
    revealUrl: {{ Illuminate\Support\Js::from(route('vault.entries.secret', $selectedEntry)) }},
    csrfToken: {{ Illuminate\Support\Js::from(csrf_token()) }},
})">
    <header class="vault-entry-detail-head">
        <div>
            <h2>{{ $selectedEntry->title }}</h2>
            <p class="vault-entry-kind">{{ $typeLabel }} · {{ Str::headline($selectedEntry->secret_kind) }}</p>
        </div>
        <div class="vault-detail-actions">
            <a href="{{ route('vault.show', [$selectedEntry, 'edit' => 1]) }}" class="vault-subtle-button"><x-icon name="pencil" /> Edit</a>
            <form method="POST" action="{{ route('vault.entries.pin', $selectedEntry) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="version" value="{{ $selectedEntry->lock_version }}">
                <button class="vault-subtle-button" type="submit"><x-icon name="pin{{ $selectedEntry->is_pinned ? '-filled' : '' }}" /> {{ $selectedEntry->is_pinned ? 'Pinned' : 'Pin' }}</button>
            </form>
            <form method="POST" action="{{ route('vault.entries.destroy', $selectedEntry) }}" data-vault-delete>
                @csrf @method('DELETE')
                <button class="vault-danger-button" type="submit"><x-icon name="trash" /> Delete</button>
            </form>
        </div>
    </header>

    <dl class="vault-detail-fields">
        @if ($selectedEntry->url)
            <div><dt>{{ $selectedEntry->type === 'api' ? 'Endpoint' : 'Website' }}</dt><dd class="vault-copy-row"><a href="{{ $selectedEntry->url }}" target="_blank" rel="noopener noreferrer">{{ $selectedEntry->url }}</a><button type="button" class="vault-icon-button" data-vault-copy-text="{{ $selectedEntry->url }}" aria-label="Copy URL"><x-icon name="copy" /></button></dd></div>
        @endif
        @if ($selectedEntry->username)
            <div><dt>{{ $selectedEntry->type === 'api' ? 'Client or account ID' : 'Username' }}</dt><dd class="vault-copy-row"><code>{{ $selectedEntry->username }}</code><button type="button" class="vault-icon-button" data-vault-copy-text="{{ $selectedEntry->username }}" aria-label="Copy username"><x-icon name="copy" /></button></dd></div>
        @endif
        @foreach (['host' => 'Host', 'port' => 'Port', 'protocol' => 'Protocol', 'database' => 'Database', 'engine' => 'Engine', 'environment' => 'Environment'] as $key => $label)
            @if (filled($metadata[$key] ?? null))<div><dt>{{ $label }}</dt><dd>{{ $key === 'protocol' || $key === 'engine' ? Str::headline($metadata[$key]) : $metadata[$key] }}</dd></div>@endif
        @endforeach
        <div>
            <dt>{{ $selectedEntry->secret_kind === 'secure_note' ? 'Secure note' : 'Secret value' }}</dt>
            <dd class="vault-secret-row">
                <input type="password" readonly value="••••••••••••••••" aria-label="Masked secret value" x-ref="secret">
                <button type="button" class="vault-subtle-button" @click="toggleReveal()" :disabled="loading" :aria-label="revealed ? 'Hide secret value' : 'Reveal secret value'">
                    <x-icon name="eye" x-show="!revealed" />
                    <x-icon name="eye-off" x-show="revealed" x-cloak />
                    <span x-text="revealed ? 'Hide' : 'Reveal'"></span>
                </button>
                <button type="button" class="vault-subtle-button" @click="copySecret()" :disabled="loading" :aria-label="copyState === 'copied' ? 'Secret copied' : (copyState === 'failed' ? 'Secret copy unavailable' : 'Copy secret value')">
                    <x-icon name="copy" x-show="copyState === 'idle'" />
                    <x-icon name="check" x-show="copyState === 'copied'" x-cloak />
                    <x-icon name="alert-triangle" x-show="copyState === 'failed'" x-cloak />
                    <span x-text="copyState === 'copied' ? 'Copied' : (copyState === 'failed' ? 'Unavailable' : 'Copy')"></span>
                </button>
            </dd>
        </div>
    </dl>

    <section class="vault-revealed-notes" x-show="notesVisible" x-cloak>
        <h3>Encrypted notes</h3>
        <p x-text="notes || 'No encrypted notes were saved for this entry.'"></p>
    </section>

    @if ($selectedEntry->tags->isNotEmpty())
        <div class="vault-detail-tags"><span>Tags</span>@foreach ($selectedEntry->tags as $tag)<a href="{{ route('vault.index', ['q' => $tag->name]) }}">#{{ $tag->name }}</a>@endforeach</div>
    @endif

    <footer class="vault-detail-footer">
        <p>Updated {{ $selectedEntry->updated_at->diffForHumans() }} · Revealed values automatically hide after 10 seconds.</p>
        <p role="status" aria-live="polite" x-text="status"></p>
    </footer>
</section>
