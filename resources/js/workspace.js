// Alpine owns local shell state. Livewire supplies Alpine; do not start another copy.
function workspaceShell(sections) {
    return {
        sections,
        query: '',
        navigationOpen: false,
        paletteOpen: false,
        accountExpanded: false,
        navigationReturn: null,
        paletteReturn: null,
        returnNavigationFocus: true,
        returnPaletteFocus: true,
        pageHideHandler: null,

        init() {
            this.pageHideHandler = () => document.documentElement.classList.remove('workspace-modal-open');
            window.addEventListener('pagehide', this.pageHideHandler);
        },

        destroy() {
            window.removeEventListener('pagehide', this.pageHideHandler);
            document.documentElement.classList.remove('workspace-modal-open');
        },

        get matchingSections() {
            const query = this.query.trim().toLocaleLowerCase();
            return this.sections.filter(item => `${item.label} ${item.description}`.toLocaleLowerCase().includes(query));
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

        openPalette(trigger) {
            this.closeNavigation(false);
            this.closeAccount(false);
            this.paletteReturn = trigger || document.activeElement;
            this.returnPaletteFocus = true;
            this.query = '';
            this.paletteOpen = true;
            if (!this.$refs.palette.open) this.$refs.palette.showModal();
            this.syncScrollLock();
            this.$nextTick(() => this.$refs.paletteInput.focus({ preventScroll: true }));
        },

        closePalette(restoreFocus = true) {
            this.returnPaletteFocus = restoreFocus;
            this.paletteOpen = false;
            if (this.$refs.palette.open) this.$refs.palette.close();
            this.syncScrollLock();
        },

        paletteClosed() {
            this.paletteOpen = false;
            this.syncScrollLock();
            if (this.returnPaletteFocus) this.$nextTick(() => {
                if (this.paletteReturn?.isConnected) this.paletteReturn.focus({ preventScroll: true });
            });
        },

        closeAccount(restoreFocus = true) {
            const account = this.$refs.account;
            if (!account?.open) return;
            account.open = false;
            this.accountExpanded = false;
            if (restoreFocus) account.querySelector('summary')?.focus({ preventScroll: true });
        },

        dismissBackdrop(event, name) {
            const dialog = this.$refs[name];
            if (event.target !== dialog) return;
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) {
                if (name === 'navigation') this.closeNavigation();
                else this.closePalette();
            }
        },

        // Keep Tab cycling inside the modal even when the browser would hand
        // sequential focus to its chrome between the last and first controls.
        cycleDialogFocus(event, name) {
            const dialog = this.$refs[name];
            if (!dialog.open) return;
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

        moveResult(direction) {
            const links = [...this.$refs.paletteResults.querySelectorAll('a')];
            if (!links.length) return;
            const current = links.indexOf(document.activeElement);
            const next = current === -1 ? (direction > 0 ? 0 : links.length - 1) : (current + direction + links.length) % links.length;
            links[next].focus();
        },

        visitFirstResult() {
            if (this.matchingSections.length) window.location.assign(this.matchingSections[0].url);
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
