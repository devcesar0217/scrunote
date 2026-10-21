// Alpine owns local shell state. Livewire supplies Alpine; do not start another copy.
function workspaceShell({ navigation, searchUrl, initialSearchQuery = '' }) {
    return {
        navigation,
        searchUrl,
        initialSearchQuery,
        searchQuery: '',
        searchResults: [],
        searchHasMore: false,
        searchState: 'idle',
        searchTimer: null,
        searchAbortController: null,
        searchRequestId: 0,
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
            clearTimeout(this.searchTimer);
            this.searchAbortController?.abort();
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

        get normalizedSearchQuery() {
            return this.searchQuery.trim();
        },

        get searchContext() {
            if (this.searchState === 'loading') return 'Searching your workspace…';
            if (this.normalizedSearchQuery.length < 2) return 'Global Search';
            return this.searchHasMore ? 'Refine your search for more matches' : 'Private workspace results';
        },

        get searchCount() {
            if (this.searchState === 'loading') return 'Searching…';
            if (this.normalizedSearchQuery.length === 0) return 'Notes · Markdown · Vault';
            if (this.normalizedSearchQuery.length < 2) return 'Type at least 2 characters';
            return `${this.searchResults.length} ${this.searchResults.length === 1 ? 'result' : 'results'}`;
        },

        get searchMessage() {
            if (this.searchState === 'loading' || this.normalizedSearchQuery.length === 0 || this.normalizedSearchQuery.length < 2) return '';
            if (this.searchState === 'error') return 'Search could not be completed. Please try again.';
            return this.searchResults.length === 0 ? 'No private workspace items match this search.' : '';
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
            this.resetSearch();
            this.paletteOpen = true;
            if (!this.$refs.palette.open) this.$refs.palette.showModal();
            this.syncScrollLock();
            this.$nextTick(() => this.$refs.paletteInput.focus({ preventScroll: true }));
        },

        closePalette(restoreFocus = true) {
            this.returnPaletteFocus = restoreFocus;
            clearTimeout(this.searchTimer);
            this.searchAbortController?.abort();
            this.paletteOpen = false;
            if (this.$refs.palette.open) this.$refs.palette.close();
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
            clearTimeout(this.searchTimer);
            this.searchAbortController?.abort();
            this.searchRequestId += 1;
            this.searchQuery = '';
            this.searchResults = [];
            this.searchHasMore = false;
            this.searchState = 'idle';
        },

        queueSearch() {
            clearTimeout(this.searchTimer);
            this.searchAbortController?.abort();
            this.searchResults = [];
            this.searchHasMore = false;
            this.searchState = 'idle';
            if (this.normalizedSearchQuery.length < 2) return;

            this.searchTimer = setTimeout(() => this.search(), 220);
        },

        async search() {
            const query = this.normalizedSearchQuery;
            if (query.length < 2) return;

            const requestId = ++this.searchRequestId;
            this.searchAbortController?.abort();
            this.searchAbortController = new AbortController();
            this.searchState = 'loading';

            const url = new URL(this.searchUrl, window.location.origin);
            url.searchParams.set('q', query);
            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    signal: this.searchAbortController.signal,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !Array.isArray(payload.results)) throw new Error('Search response was invalid.');
                if (requestId !== this.searchRequestId) return;

                this.searchResults = payload.results;
                this.searchHasMore = payload.has_more === true;
                this.searchState = 'ready';
            } catch (error) {
                if (error.name === 'AbortError' || requestId !== this.searchRequestId) return;
                this.searchResults = [];
                this.searchHasMore = false;
                this.searchState = 'error';
            }
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
            if (this.searchResults.length) window.location.assign(this.searchResults[0].url);
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
