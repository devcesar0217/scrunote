// Alpine owns local shell state. Livewire supplies Alpine; do not start another copy.
function workspaceShell({ navigation }) {
    return {
        navigation,
        navigationOpen: false,
        paletteOpen: false,
        accountExpanded: false,
        navigationReturn: null,
        paletteReturn: null,
        returnNavigationFocus: true,
        returnPaletteFocus: true,
        pageHideHandler: null,
        theme: 'dark',
        themeChangedHandler: null,

        init() {
            this.theme = this.readTheme();
            this.applyTheme(this.theme);
            this.pageHideHandler = () => document.documentElement.classList.remove('workspace-modal-open');
            this.themeChangedHandler = event => this.setTheme(event.detail);
            window.addEventListener('pagehide', this.pageHideHandler);
            window.addEventListener('scrunote-theme-change', this.themeChangedHandler);
        },

        destroy() {
            window.removeEventListener('pagehide', this.pageHideHandler);
            window.removeEventListener('scrunote-theme-change', this.themeChangedHandler);
            document.documentElement.classList.remove('workspace-modal-open');
        },

        readTheme() {
            try {
                const stored = window.localStorage.getItem('scrunote.theme');
                return stored === 'light' || stored === 'dark' ? stored : 'dark';
            } catch (_) {
                return 'dark';
            }
        },

        applyTheme(theme) {
            document.documentElement.dataset.theme = theme;
        },

        setTheme(theme) {
            if (theme !== 'light' && theme !== 'dark') return;
            this.theme = theme;
            this.applyTheme(theme);
            try {
                window.localStorage.setItem('scrunote.theme', theme);
            } catch (_) {
                // The live preference still works when storage is unavailable.
            }
        },

        syncScrollLock() {
            document.documentElement.classList.toggle('workspace-modal-open', this.navigationOpen || this.paletteOpen);
        },

        openNavigation(trigger) {
            this.closePalette(false);
            this.closeAccount(false);
            this.navigationReturn = trigger || document.activeElement;
            this.returnNavigationFocus = true;
            this.navigationOpen = true;
            if (!this.$refs.navigation.open) this.$refs.navigation.showModal();
            this.syncScrollLock();
        },

        closeNavigation(restoreFocus = true) {
            this.returnNavigationFocus = restoreFocus;
            this.navigationOpen = false;
            if (this.$refs.navigation.open) this.$refs.navigation.close();
            this.syncScrollLock();
        },

        navigationClosed() {
            this.navigationOpen = false;
            this.syncScrollLock();
            if (this.returnNavigationFocus) this.$nextTick(() => {
                if (this.navigationReturn?.getClientRects().length) this.navigationReturn.focus({ preventScroll: true });
                else document.querySelector('.workspace-desktop-rail [aria-current="page"]')?.focus({ preventScroll: true });
            });
        },

        paletteDialog() {
            // Keep the native dialog outside the Livewire component boundary.
            // Alpine refs do not cross that boundary reliably after a Livewire morph.
            return this.$refs.palette || document.getElementById('workspace-palette');
        },

        openPalette(trigger) {
            const dialog = this.paletteDialog();
            if (!dialog) return;

            this.closeNavigation(false);
            this.closeAccount(false);
            this.paletteReturn = trigger || document.activeElement;
            this.returnPaletteFocus = true;
            this.resetSearch();
            this.paletteOpen = true;
            if (!dialog.open) dialog.showModal();
            this.syncScrollLock();
            this.$nextTick(() => document.getElementById('workspace-search-query')?.focus({ preventScroll: true }));
        },

        closePalette(restoreFocus = true) {
            this.returnPaletteFocus = restoreFocus;
            this.paletteOpen = false;
            const dialog = this.paletteDialog();
            if (dialog?.open) dialog.close();
            this.syncScrollLock();
        },

        paletteClosed() {
            const shouldRestoreFocus = this.returnPaletteFocus;
            this.paletteOpen = false;
            this.syncScrollLock();
            if (shouldRestoreFocus) this.$nextTick(() => {
                // A keyboard shortcut can reopen the dialog before this close
                // handler's next tick runs; never steal focus from the new dialog.
                if (!this.paletteOpen && this.paletteReturn?.isConnected) this.paletteReturn.focus({ preventScroll: true });
            });
        },

        resetSearch() {
            // The palette itself is a Livewire component. Alpine owns only the
            // dialog/focus state and asks Livewire to reset its server state.
            window.Livewire?.dispatch('reset-global-search');
        },

        closeAccount(restoreFocus = true) {
            const account = this.$refs.account;
            if (!account?.open) return;
            account.open = false;
            this.accountExpanded = false;
            if (restoreFocus) account.querySelector('summary')?.focus({ preventScroll: true });
        },

        dismissBackdrop(event, name) {
            const dialog = this.$refs[name] || document.getElementById(`workspace-${name}`);
            if (!dialog || event.target !== dialog) return;
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) {
                if (name === 'navigation') this.closeNavigation();
                else this.closePalette();
            }
        },

        // Keep Tab cycling inside the modal even when the browser would hand
        // sequential focus to its chrome between the last and first controls.
        cycleDialogFocus(event, name) {
            const dialog = this.$refs[name] || document.getElementById(`workspace-${name}`);
            if (!dialog?.open) return;
            const controls = [...dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
                .filter(element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
            event.preventDefault();
            if (!controls.length) { dialog.focus(); return; }
            const current = controls.indexOf(document.activeElement);
            const next = event.shiftKey
                ? (current <= 0 ? controls.length - 1 : current - 1)
                : (current < 0 || current === controls.length - 1 ? 0 : current + 1);
            controls[next].focus();
        },

        movePaletteResult(direction) {
            const results = this.paletteDialog()?.querySelector('.workspace-palette-results');
            const links = [...(results?.querySelectorAll('a') || [])];
            if (!links.length) return;
            const current = links.indexOf(document.activeElement);
            const next = current === -1 ? (direction > 0 ? 0 : links.length - 1) : (current + direction + links.length) % links.length;
            links[next].focus();
        },

        visitFirstPaletteResult() {
            const first = this.paletteDialog()?.querySelector('.workspace-palette-results a[href]');
            if (first) window.location.assign(first.href);
        },

        handleShortcut(event) {
            if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && !event.repeat && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                if (this.paletteOpen) this.closePalette();
                else this.openPalette(document.activeElement);
            }
        },

        handleResize() {
            if (window.matchMedia('(min-width: 1024px)').matches && this.navigationOpen) this.closeNavigation();
        },

        restorePageState() {
            this.closeNavigation(false);
            this.closePalette(false);
            this.closeAccount(false);
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('workspaceShell', workspaceShell);
}, { once: true });
