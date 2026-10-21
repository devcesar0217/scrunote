const HEARTBEAT_INTERVAL = 60_000;
const REVEAL_DURATION = 10_000;

async function copyToClipboard(value) {
    if (navigator.clipboard?.writeText && window.isSecureContext) {
        await navigator.clipboard.writeText(value);

        return;
    }

    const fallback = document.createElement('textarea');
    fallback.value = value;
    fallback.setAttribute('readonly', '');
    fallback.style.position = 'fixed';
    fallback.style.opacity = '0';
    document.body.append(fallback);
    fallback.select();
    const copied = document.execCommand('copy');
    fallback.remove();
    if (!copied) throw new Error('Clipboard access was not available.');
}

function vaultWorkspace({ unlocked, idleMilliseconds, lockUrl, heartbeatUrl, csrfToken, projectId = null, newEntryUrl }) {
    return {
        unlocked,
        idleMilliseconds,
        lockUrl,
        heartbeatUrl,
        csrfToken,
        projectId,
        newEntryUrl,
        lockTimer: null,
        lastHeartbeatAt: 0,
        activityHandler: null,

        get newEntryUrlForProject() {
            const url = new URL(this.newEntryUrl, window.location.origin);
            if (Number.isInteger(this.projectId) || (typeof this.projectId === 'string' && /^\d+$/.test(this.projectId))) {
                url.searchParams.set('project', this.projectId);
            } else {
                url.searchParams.delete('project');
            }

            return `${url.pathname}${url.search}${url.hash}`;
        },

        init() {
            if (!this.unlocked) return;
            this.activityHandler = () => this.recordActivity();
            ['pointerdown', 'keydown', 'touchstart'].forEach(event => window.addEventListener(event, this.activityHandler, { passive: true }));
            document.addEventListener('visibilitychange', this.activityHandler);
            this.recordActivity();
        },

        destroy() {
            clearTimeout(this.lockTimer);
            if (!this.activityHandler) return;
            ['pointerdown', 'keydown', 'touchstart'].forEach(event => window.removeEventListener(event, this.activityHandler));
            document.removeEventListener('visibilitychange', this.activityHandler);
        },

        recordActivity() {
            if (!this.unlocked || document.visibilityState === 'hidden') return;
            clearTimeout(this.lockTimer);
            this.lockTimer = setTimeout(() => this.lockForInactivity(), this.idleMilliseconds);
            if (Date.now() - this.lastHeartbeatAt >= HEARTBEAT_INTERVAL) this.renewServerSession();
        },

        async renewServerSession() {
            this.lastHeartbeatAt = Date.now();
            try {
                const response = await fetch(this.heartbeatUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (response.status === 423) window.location.reload();
            } catch (_) {
                // The server-side expiry remains the security boundary if a heartbeat cannot be sent.
            }
        },

        async lockForInactivity() {
            try {
                await fetch(this.lockUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                });
            } finally {
                window.location.reload();
            }
        },
    };
}

function vaultEntryForm({ type, secretKind }) {
    return {
        type,
        secretKind,
        secretVisible: false,
        length: 24,
        upper: true,
        lower: true,
        numbers: true,
        symbols: true,

        init() {
            this.$watch('type', value => {
                if (value === 'secure_note') this.secretKind = 'secure_note';
                else if (value === 'api' && !['api_key', 'token'].includes(this.secretKind)) this.secretKind = 'api_key';
                else if (!['password', 'api_key', 'token'].includes(this.secretKind)) this.secretKind = 'password';
            });
        },

        toggleSecret() {
            this.secretVisible = !this.secretVisible;
            this.$refs.secret.type = this.secretVisible ? 'text' : 'password';
        },

        generateSecret() {
            const classes = [
                this.upper ? 'ABCDEFGHJKLMNPQRSTUVWXYZ' : '',
                this.lower ? 'abcdefghijkmnopqrstuvwxyz' : '',
                this.numbers ? '23456789' : '',
                this.symbols ? '!#$%&()*+,-./:;<=>?@[]^_{|}~' : '',
            ].filter(Boolean);
            if (!classes.length) classes.push('abcdefghijkmnopqrstuvwxyz');

            const requestedLength = Number.isFinite(this.length) ? this.length : 24;
            const length = Math.max(12, Math.min(128, Math.round(requestedLength)));
            const characters = [...classes.map(characterSet => this.randomCharacter(characterSet))];
            const pool = classes.join('');
            while (characters.length < length) characters.push(this.randomCharacter(pool));

            for (let index = characters.length - 1; index > 0; index -= 1) {
                const swapIndex = this.randomIndex(index + 1);
                [characters[index], characters[swapIndex]] = [characters[swapIndex], characters[index]];
            }

            this.$refs.secret.value = characters.join('');
            this.secretVisible = false;
            this.$refs.secret.type = 'password';
        },

        randomCharacter(characters) {
            return characters[this.randomIndex(characters.length)];
        },

        randomIndex(maximum) {
            const limit = Math.floor(0x100000000 / maximum) * maximum;
            const values = new Uint32Array(1);
            do window.crypto.getRandomValues(values); while (values[0] >= limit);

            return values[0] % maximum;
        },
    };
}

function vaultSecret({ revealUrl, csrfToken }) {
    return {
        revealUrl,
        csrfToken,
        secret: '',
        notes: '',
        revealed: false,
        notesVisible: false,
        loading: false,
        status: '',
        hideTimer: null,
        copyTimer: null,
        copyState: 'idle',

        destroy() {
            clearTimeout(this.hideTimer);
            clearTimeout(this.copyTimer);
            this.hide();
        },

        async loadSecret() {
            if (this.secret !== '') return true;
            this.loading = true;
            this.status = 'Loading encrypted value…';
            try {
                const response = await fetch(this.revealUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (response.status === 423) {
                    window.location.reload();

                    return false;
                }
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || typeof payload.secret !== 'string' || typeof payload.notes !== 'string') {
                    this.status = payload.message || 'The encrypted value could not be retrieved.';

                    return false;
                }
                this.secret = payload.secret;
                this.notes = payload.notes;
                this.status = '';

                return true;
            } catch (_) {
                this.status = 'Connection problem. The encrypted value was not revealed.';

                return false;
            } finally {
                this.loading = false;
            }
        },

        async toggleReveal() {
            if (this.revealed) {
                this.hide();

                return;
            }
            if (!await this.loadSecret()) return;
            this.$refs.secret.value = this.secret;
            this.$refs.secret.type = 'text';
            this.revealed = true;
            this.notesVisible = true;
            this.status = 'Secret revealed. It will hide automatically in 10 seconds.';
            clearTimeout(this.hideTimer);
            this.hideTimer = setTimeout(() => this.hide(), REVEAL_DURATION);
        },

        async copySecret() {
            if (!await this.loadSecret()) return;
            clearTimeout(this.copyTimer);
            try {
                await copyToClipboard(this.secret);
                this.copyState = 'copied';
                this.status = 'Secret copied. Clipboard clearing is controlled by your browser or operating system.';
            } catch (_) {
                this.copyState = 'failed';
                this.status = 'Clipboard access was not available. Reveal the secret and copy it manually.';
            }
            this.copyTimer = setTimeout(() => {
                this.copyState = 'idle';
            }, 1800);
        },

        hide() {
            clearTimeout(this.hideTimer);
            clearTimeout(this.copyTimer);
            this.copyState = 'idle';
            if (this.$refs.secret) {
                this.$refs.secret.type = 'password';
                this.$refs.secret.value = '••••••••••••••••';
            }
            this.secret = '';
            this.notes = '';
            this.revealed = false;
            this.notesVisible = false;
        },
    };
}

function registerVaultAlpineData() {
    window.Alpine.data('vaultWorkspace', vaultWorkspace);
    window.Alpine.data('vaultEntryForm', vaultEntryForm);
    window.Alpine.data('vaultSecret', vaultSecret);
}

if (window.Alpine) registerVaultAlpineData();
else document.addEventListener('alpine:init', registerVaultAlpineData, { once: true });

document.addEventListener('click', async event => {
    const copyButton = event.target.closest('[data-vault-copy-text]');
    if (!copyButton) return;

    try {
        await copyToClipboard(copyButton.dataset.vaultCopyText || '');
        copyButton.setAttribute('data-vault-copied', 'true');
        setTimeout(() => copyButton.removeAttribute('data-vault-copied'), 1800);
    } catch (_) {
        copyButton.setAttribute('data-vault-copy-failed', 'true');
        setTimeout(() => copyButton.removeAttribute('data-vault-copy-failed'), 1800);
    }
});

document.addEventListener('submit', event => {
    if (!event.target.matches('[data-vault-delete]')) return;
    if (!window.confirm('Delete this encrypted Vault entry permanently? This cannot be undone.')) event.preventDefault();
});
