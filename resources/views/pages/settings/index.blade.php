@extends('layouts.app')
@section('title', 'Settings · Scrunote')

@section('content')
    <section class="settings-page" x-data="settingsPage()" aria-labelledby="settings-heading">
        <header class="workspace-section-header settings-header">
            <div>
                <p class="workspace-eyebrow">Preferences</p>
                <h1 id="settings-heading">Settings</h1>
            </div>
        </header>

        @if (session('settings_status'))
            <p class="settings-feedback settings-feedback-success" role="status"><x-icon name="circle-check" /> {{ session('settings_status') }}</p>
        @endif
        @if ($errors->any())
            <div class="settings-feedback settings-feedback-error" role="alert">
                <x-icon name="alert-triangle" />
                <div>
                    <strong>Settings action could not be completed.</strong>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            </div>
        @endif

        <section class="settings-card" aria-labelledby="appearance-heading">
            <div class="settings-card-icon settings-card-icon-theme"><x-icon name="palette" /></div>
            <div class="settings-card-copy">
                <h2 id="appearance-heading">Appearance</h2>
                <p>Choose a color theme for your workspace. This preference is saved locally and restored after you sign in.</p>
                <p class="settings-card-hint">Your selected theme is stored only on this browser and device.</p>
            </div>
            <div class="settings-theme-options" aria-label="Color theme">
                <button type="button" class="settings-theme-option" :class="{ 'is-selected': theme === 'dark' }"
                    :aria-pressed="theme === 'dark'" @click="setTheme('dark')">
                    <x-icon name="moon" /> Dark
                </button>
                <button type="button" class="settings-theme-option" :class="{ 'is-selected': theme === 'light' }"
                    :aria-pressed="theme === 'light'" @click="setTheme('light')">
                    <x-icon name="sun" /> Light
                </button>
            </div>
        </section>

        <section class="settings-card settings-backup-card" aria-labelledby="backup-heading">
            <div class="settings-card-icon settings-card-icon-backup"><x-icon name="database-export" /></div>
            <div class="settings-card-copy">
                <h2 id="backup-heading">Encrypted workspace backup</h2>
                <p>Protect a complete workspace snapshot with a unique backup password. It includes Projects, Notes, Markdown, tags, and Vault ciphertext.</p>
                <p class="settings-card-warning"><x-icon name="alert-triangle" /> Vault secrets are copied only as encrypted ciphertext. Restoring them still requires the Vault’s original master password.</p>
                <p class="settings-card-hint">Backups are encrypted before download and are never retained on this server. Restoring replaces this account’s current workspace.</p>
            </div>
            <div class="settings-backup-actions">
                <button type="button" class="settings-primary-button" @click="openBackupDialog('create', $el)"><x-icon name="lock" /> Create backup</button>
                <button type="button" class="workspace-secondary-button" @click="openBackupDialog('restore', $el)"><x-icon name="database-import" /> Restore backup</button>
            </div>
        </section>

        <noscript>
            <section class="settings-noscript" aria-label="Backup controls without JavaScript">
                <h2>Encrypted workspace backup</h2>
                <details>
                    <summary>Create backup</summary>
                    <form method="POST" action="{{ route('settings.backup.create') }}">
                        @csrf
                        <label>Backup password <input type="password" name="backup_password" minlength="12" required autocomplete="new-password"></label>
                        <label>Confirm backup password <input type="password" name="backup_password_confirmation" minlength="12" required autocomplete="new-password"></label>
                        <label><input type="checkbox" name="backup_acknowledgement" value="1" required> I will protect the encrypted backup and its password separately.</label>
                        <button type="submit" class="settings-primary-button">Download encrypted backup</button>
                    </form>
                </details>
                <details>
                    <summary>Restore backup</summary>
                    <form method="POST" action="{{ route('settings.backup.restore') }}" enctype="multipart/form-data">
                        @csrf
                        <label>Backup file <input type="file" name="backup_file" accept=".scrunote,application/json" required></label>
                        <label>Backup password <input type="password" name="backup_password" minlength="12" required autocomplete="current-password"></label>
                        <label><input type="checkbox" name="replace_workspace" value="1" required> I understand this replaces my current workspace.</label>
                        <button type="submit" class="workspace-secondary-button">Restore encrypted backup</button>
                    </form>
                </details>
            </section>
        </noscript>

        <dialog class="settings-dialog" x-ref="backupCreateDialog" aria-labelledby="backup-create-heading"
            @click="dismissBackupBackdrop($event, 'create')" @close="backupDialogClosed()">
            <form method="POST" action="{{ route('settings.backup.create') }}" class="settings-dialog-form" @submit="closeBackupDialog(false)">
                @csrf
                <header class="settings-dialog-header">
                    <span class="settings-card-icon settings-card-icon-backup"><x-icon name="lock" /></span>
                    <div><h2 id="backup-create-heading">Create encrypted backup</h2><p>The file contains a complete workspace snapshot. Use a unique password of at least 12 characters.</p></div>
                    <button type="button" class="workspace-icon-button" @click="closeBackupDialog()" aria-label="Close create backup dialog"><x-icon name="x" /></button>
                </header>
                <div class="settings-dialog-fields">
                    <label for="backup-password">Backup password</label>
                    <div class="settings-password-control">
                        <input id="backup-password" :type="backupPasswordVisible ? 'text' : 'password'" name="backup_password" minlength="12" maxlength="1024" required autocomplete="new-password" x-ref="backupPassword" placeholder="Use a unique password (12+ characters)">
                        <button type="button" @click="backupPasswordVisible = !backupPasswordVisible" :aria-label="backupPasswordVisible ? 'Hide backup password' : 'Show backup password'" :aria-pressed="backupPasswordVisible"><i :class="backupPasswordVisible ? 'ti ti-eye-off' : 'ti ti-eye'" aria-hidden="true"></i></button>
                    </div>
                    <label for="backup-password-confirmation">Confirm backup password</label>
                    <div class="settings-password-control">
                        <input id="backup-password-confirmation" :type="backupPasswordVisible ? 'text' : 'password'" name="backup_password_confirmation" minlength="12" maxlength="1024" required autocomplete="new-password" placeholder="Re-enter the backup password">
                        <button type="button" @click="backupPasswordVisible = !backupPasswordVisible" :aria-label="backupPasswordVisible ? 'Hide backup password' : 'Show backup password'" :aria-pressed="backupPasswordVisible"><i :class="backupPasswordVisible ? 'ti ti-eye-off' : 'ti ti-eye'" aria-hidden="true"></i></button>
                    </div>
                    <label class="settings-confirmation"><input type="checkbox" name="backup_acknowledgement" value="1" required> <span>I understand the backup includes my workspace data, including Vault ciphertext. I will protect the file and keep its password separate.</span></label>
                    <p class="settings-dialog-note">The password is used only to encrypt this download and is never stored by Scrunote.</p>
                </div>
                <footer class="settings-dialog-footer"><button type="button" class="workspace-secondary-button" @click="closeBackupDialog()">Cancel</button><button type="submit" class="settings-primary-button"><x-icon name="lock" /> Choose backup location</button></footer>
            </form>
        </dialog>

        <dialog class="settings-dialog" x-ref="backupRestoreDialog" aria-labelledby="backup-restore-heading"
            @click="dismissBackupBackdrop($event, 'restore')" @close="backupDialogClosed()">
            <form method="POST" action="{{ route('settings.backup.restore') }}" enctype="multipart/form-data" class="settings-dialog-form">
                @csrf
                <header class="settings-dialog-header">
                    <span class="settings-card-icon settings-card-icon-backup"><x-icon name="database-import" /></span>
                    <div><h2 id="backup-restore-heading">Restore encrypted backup</h2><p>Unlock a workspace snapshot, validate it, then replace this account’s current workspace.</p></div>
                    <button type="button" class="workspace-icon-button" @click="closeBackupDialog()" aria-label="Close restore backup dialog"><x-icon name="x" /></button>
                </header>
                <div class="settings-dialog-fields">
                    <label for="restore-backup-file">Backup file</label>
                    <input id="restore-backup-file" type="file" name="backup_file" accept=".scrunote,application/json" required x-ref="restoreFile">
                    <label for="restore-backup-password">Backup password</label>
                    <div class="settings-password-control">
                        <input id="restore-backup-password" :type="restorePasswordVisible ? 'text' : 'password'" name="backup_password" minlength="12" maxlength="1024" required autocomplete="current-password" x-ref="restorePassword" placeholder="Enter the backup password">
                        <button type="button" @click="restorePasswordVisible = !restorePasswordVisible" :aria-label="restorePasswordVisible ? 'Hide backup password' : 'Show backup password'" :aria-pressed="restorePasswordVisible"><i :class="restorePasswordVisible ? 'ti ti-eye-off' : 'ti ti-eye'" aria-hidden="true"></i></button>
                    </div>
                    <label class="settings-confirmation settings-confirmation-danger"><input type="checkbox" name="replace_workspace" value="1" required> <span>I understand restoring permanently replaces all of this account’s current Projects, Notes, Markdown, tags, and Vault data.</span></label>
                    <p class="settings-dialog-note">A successful restore locks the Vault. You will need the original Vault master password to access restored Vault entries.</p>
                </div>
                <footer class="settings-dialog-footer"><button type="button" class="workspace-secondary-button" @click="closeBackupDialog()">Cancel</button><button type="submit" class="settings-danger-button"><x-icon name="database-import" /> Replace workspace</button></footer>
            </form>
        </dialog>
    </section>
@endsection
