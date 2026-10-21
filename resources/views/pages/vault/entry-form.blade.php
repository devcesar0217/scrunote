@php
    $editing = $selectedEntry !== null;
    $metadata = $selectedEntry?->metadata ?? [];
    $formType = old('type', $selectedEntry?->type ?? 'login');
    $formSecretKind = old('secret_kind', $selectedEntry?->secret_kind ?? ($formType === 'secure_note' ? 'secure_note' : ($formType === 'api' ? 'api_key' : 'password')));
@endphp

<form class="vault-entry-form" method="POST" action="{{ $editing ? route('vault.entries.update', $selectedEntry) : route('vault.entries.store') }}"
    x-data="vaultEntryForm({
        type: {{ Illuminate\Support\Js::from($formType) }},
        secretKind: {{ Illuminate\Support\Js::from($formSecretKind) }},
    })">
    @csrf
    @if ($editing)
        @method('PATCH')
        <input type="hidden" name="version" value="{{ old('version', $selectedEntry->lock_version) }}">
    @endif

    <header class="vault-entry-form-head">
        <div>
            <p class="vault-kicker">{{ $editing ? 'Edit encrypted entry' : 'New vault entry' }}</p>
            <h2>{{ $editing ? $selectedEntry->title : 'New vault entry' }}</h2>
            <p>Secret fields are encrypted only when this form is submitted.</p>
        </div>
        <a href="{{ $editing ? route('vault.show', $selectedEntry) : route('vault.index') }}" class="vault-subtle-button">Cancel</a>
    </header>

    @if ($errors->any())
        <div class="vault-form-errors" role="alert" tabindex="-1">
            <x-icon name="alert-triangle" />
            <div><strong>Could not save this Vault entry.</strong><p>{{ $errors->first() }}</p></div>
        </div>
    @endif

    <div class="vault-entry-fields">
        <label class="vault-field vault-field-wide">
            <span>Service or entry name</span>
            <input type="text" name="title" value="{{ old('title', $selectedEntry?->title) }}" maxlength="160"
                placeholder="Example: Production database" autocomplete="off" required autofocus>
        </label>

        <label class="vault-field">
            <span>Entry type</span>
            <select name="type" x-model="type" required>
                <option value="login" @selected($formType === 'login')>Login</option>
                <option value="server" @selected($formType === 'server')>Server</option>
                <option value="database" @selected($formType === 'database')>Database</option>
                <option value="api" @selected($formType === 'api')>API Credential</option>
                <option value="secure_note" @selected($formType === 'secure_note')>Secure Note</option>
            </select>
        </label>

        <label class="vault-field" x-show="type !== 'secure_note'">
            <span x-text="type === 'api' ? 'Endpoint URL (optional)' : 'Website URL (optional)'"></span>
            <input type="url" name="url" value="{{ old('url', $selectedEntry?->url) }}" maxlength="2048"
                placeholder="https://example.com" autocomplete="url">
        </label>

        <label class="vault-field" x-show="type !== 'secure_note'">
            <span x-text="type === 'api' ? 'Client or account ID (optional)' : 'Username or account ID (optional)'"></span>
            <input type="text" name="username" value="{{ old('username', $selectedEntry?->username) }}" maxlength="320"
                placeholder="name@example.com" autocomplete="username">
        </label>

        <label class="vault-field" x-show="type !== 'secure_note'">
            <span>Secret type</span>
            <select name="secret_kind" x-model="secretKind" required>
                <option value="password" @selected($formSecretKind === 'password')>Password</option>
                <option value="api_key" @selected($formSecretKind === 'api_key') x-show="type === 'api'">API key</option>
                <option value="token" @selected($formSecretKind === 'token') x-show="type === 'api'">Token</option>
                <option value="secure_note" @selected($formSecretKind === 'secure_note') x-show="type === 'secure_note'">Secure note</option>
            </select>
        </label>

        <label class="vault-field" x-show="type === 'server' || type === 'database'">
            <span>Host</span>
            <input type="text" name="metadata[host]" value="{{ old('metadata.host', $metadata['host'] ?? '') }}" maxlength="255" placeholder="db.internal.example">
        </label>

        <label class="vault-field" x-show="type === 'server' || type === 'database'">
            <span>Port</span>
            <input type="number" name="metadata[port]" value="{{ old('metadata.port', $metadata['port'] ?? '') }}" min="1" max="65535" inputmode="numeric" placeholder="3306">
        </label>

        <label class="vault-field" x-show="type === 'server'">
            <span>Protocol</span>
            <select name="metadata[protocol]">
                <option value="">Select protocol</option>
                @foreach (['ssh' => 'SSH', 'sftp' => 'SFTP', 'ftp' => 'FTP', 'rdp' => 'RDP'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('metadata.protocol', $metadata['protocol'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="vault-field" x-show="type === 'database'">
            <span>Database name</span>
            <input type="text" name="metadata[database]" value="{{ old('metadata.database', $metadata['database'] ?? '') }}" maxlength="255" placeholder="application">
        </label>

        <label class="vault-field" x-show="type === 'database'">
            <span>Engine</span>
            <select name="metadata[engine]">
                <option value="">Select engine</option>
                @foreach (['mysql' => 'MySQL', 'postgresql' => 'PostgreSQL', 'mariadb' => 'MariaDB', 'sql_server' => 'SQL Server', 'sqlite' => 'SQLite'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('metadata.engine', $metadata['engine'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="vault-field" x-show="type === 'api'">
            <span>Environment</span>
            <input type="text" name="metadata[environment]" value="{{ old('metadata.environment', $metadata['environment'] ?? '') }}" maxlength="80" placeholder="Production">
        </label>

        <label class="vault-field vault-field-wide">
            <span x-text="type === 'secure_note' ? 'Secure note' : 'Secret value'"></span>
            <div class="vault-secret-input">
                <input x-ref="secret" type="password" name="secret" maxlength="20000" autocomplete="new-password"
                    :placeholder="{{ Illuminate\Support\Js::from($editing ? 'Leave blank to keep the current secret' : 'Enter a password, API key, token, or secure note') }}" {{ $editing ? '' : 'required' }}>
                <button type="button" class="vault-icon-button" @click="toggleSecret()" :aria-label="secretVisible ? 'Hide secret value' : 'Show secret value'">
                    <x-icon name="eye" x-show="!secretVisible" /><x-icon name="eye-off" x-show="secretVisible" x-cloak />
                </button>
            </div>
        </label>

        <div class="vault-generator vault-field-wide" x-show="type !== 'secure_note'">
            <div class="vault-generator-options">
                <label>Length <input type="number" x-model.number="length" min="12" max="128" inputmode="numeric"></label>
                <label><input type="checkbox" x-model="upper"> Uppercase</label>
                <label><input type="checkbox" x-model="lower"> Lowercase</label>
                <label><input type="checkbox" x-model="numbers"> Numbers</label>
                <label><input type="checkbox" x-model="symbols"> Symbols</label>
            </div>
            <button type="button" class="vault-subtle-button" @click="generateSecret()"><x-icon name="refresh" /> Generate strong password</button>
            <span>Generated in your browser. It is not saved until you save this entry.</span>
        </div>

        <label class="vault-field vault-field-wide">
            <span>Encrypted notes (optional)</span>
            <textarea name="notes" rows="4" maxlength="20000" x-ref="notes" placeholder="Private setup, recovery, or rotation details">{{ old('notes') }}</textarea>
        </label>

        @if ($editing)
            <label class="vault-check vault-field-wide"><input type="checkbox" name="clear_notes" value="1" @checked(old('clear_notes'))> Clear the existing encrypted notes</label>
        @endif

        <label class="vault-field vault-field-wide">
            <span>Tags, separated by commas</span>
            <input type="text" name="tags" value="{{ old('tags', $selectedEntry?->tags->pluck('name')->implode(', ')) }}" maxlength="650"
                placeholder="production, database, personal" autocomplete="off">
        </label>
    </div>

    <footer class="vault-entry-form-footer">
        <p><x-icon name="shield-lock" /> Secret values and notes are encrypted at rest and are not searchable.</p>
        <div>
            <a href="{{ $editing ? route('vault.show', $selectedEntry) : route('vault.index') }}" class="vault-subtle-button">Cancel</a>
            <button type="submit" class="vault-primary-button"><x-icon name="device-floppy" /> {{ $editing ? 'Save changes' : 'Save entry' }}</button>
        </div>
    </footer>
</form>
