// Settings is deliberately local-first: theme selection stays in this browser,
// while backup and restore remain regular CSRF-protected server form posts.
function settingsPage() {
    return {
        theme: document.documentElement.dataset.theme === 'light' ? 'light' : 'dark',
        backupDialog: null,
        backupReturn: null,
        restoreBackupFocus: true,
        backupPasswordVisible: false,
        restorePasswordVisible: false,
        themeListener: null,

        init() {
            this.themeListener = event => {
                if (event.detail === 'dark' || event.detail === 'light') this.theme = event.detail;
            };
            window.addEventListener('scrunote-theme-change', this.themeListener);
        },

        destroy() {
            window.removeEventListener('scrunote-theme-change', this.themeListener);
            document.documentElement.classList.remove('workspace-modal-open');
        },

        setTheme(theme) {
            if (theme !== 'dark' && theme !== 'light') return;
            this.theme = theme;
            window.dispatchEvent(new CustomEvent('scrunote-theme-change', { detail: theme }));
        },

        openBackupDialog(kind, trigger = document.activeElement) {
            const dialog = kind === 'create' ? this.$refs.backupCreateDialog : this.$refs.backupRestoreDialog;
            if (!dialog || dialog.open) return;
            this.backupReturn = trigger;
            this.restoreBackupFocus = true;
            this.backupDialog = kind;
            this.backupPasswordVisible = false;
            this.restorePasswordVisible = false;
            dialog.showModal();
            document.documentElement.classList.add('workspace-modal-open');
            this.$nextTick(() => (kind === 'create' ? this.$refs.backupPassword : this.$refs.restoreFile)?.focus({ preventScroll: true }));
        },

        closeBackupDialog(restoreFocus = true) {
            this.restoreBackupFocus = restoreFocus;
            const dialog = this.backupDialog === 'create' ? this.$refs.backupCreateDialog : this.$refs.backupRestoreDialog;
            if (dialog?.open) dialog.close();
        },

        backupDialogClosed() {
            const shouldRestore = this.restoreBackupFocus;
            this.backupDialog = null;
            document.documentElement.classList.remove('workspace-modal-open');
            if (shouldRestore) this.$nextTick(() => {
                if (this.backupDialog === null && this.backupReturn?.isConnected) this.backupReturn.focus({ preventScroll: true });
            });
        },

        dismissBackupBackdrop(event, kind) {
            const dialog = kind === 'create' ? this.$refs.backupCreateDialog : this.$refs.backupRestoreDialog;
            if (event.target !== dialog) return;
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) this.closeBackupDialog();
        },
    };
}

function registerSettingsAlpineData() {
    window.Alpine.data('settingsPage', settingsPage);
}

if (window.Alpine) registerSettingsAlpineData();
else document.addEventListener('alpine:init', registerSettingsAlpineData, { once: true });
