<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\VaultEntryController;
use App\Http\Controllers\VaultSecretController;
use App\Http\Controllers\VaultSessionController;
use App\Http\Controllers\WorkspaceBackupController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureVaultIsUnlocked;
use App\Http\Middleware\PrivateResponseHeaders;
use Illuminate\Support\Facades\Route;

Route::middleware(PrivateResponseHeaders::class)->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login-ip')->name('login.store');

        Route::get('/register', [RegisterController::class, 'show'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:registration')->name('register.store');
    });

    Route::middleware(['auth', EnsureUserIsActive::class, 'auth.session'])->group(function (): void {
        Route::get('/', WorkspaceController::class)->name('dashboard');
        Route::get('/search', GlobalSearchController::class)->name('search.index');

        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
        Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
        Route::get('/notes/{note}', [NoteController::class, 'show'])->name('notes.show');
        Route::patch('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
        Route::patch('/notes/{note}/pin', [NoteController::class, 'togglePin'])->name('notes.pin');
        Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

        Route::get('/markdown', [DocumentController::class, 'index'])->name('markdown.index');
        Route::post('/markdown', [DocumentController::class, 'store'])->name('markdown.store');
        Route::get('/markdown/{document}', [DocumentController::class, 'show'])->name('markdown.show');
        Route::patch('/markdown/{document}', [DocumentController::class, 'update'])->name('markdown.update');
        Route::patch('/markdown/{document}/pin', [DocumentController::class, 'togglePin'])->name('markdown.pin');
        Route::get('/markdown/{document}/export', [DocumentController::class, 'export'])->name('markdown.export');
        Route::delete('/markdown/{document}', [DocumentController::class, 'destroy'])->name('markdown.destroy');

        Route::get('/vault', [VaultController::class, 'index'])->name('vault.index');
        Route::post('/vault/setup', [VaultSessionController::class, 'setup'])->middleware('throttle:vault-unlock')->name('vault.setup');
        Route::post('/vault/unlock', [VaultSessionController::class, 'unlock'])->middleware('throttle:vault-unlock')->name('vault.unlock');
        Route::post('/vault/lock', [VaultSessionController::class, 'lock'])->name('vault.lock');
        Route::post('/vault/heartbeat', [VaultSessionController::class, 'heartbeat'])->name('vault.heartbeat');
        Route::get('/vault/{vaultEntry}', [VaultController::class, 'show'])->name('vault.show');

        Route::middleware(EnsureVaultIsUnlocked::class)->group(function (): void {
            Route::post('/vault/entries', [VaultEntryController::class, 'store'])->name('vault.entries.store');
            Route::patch('/vault/entries/{vaultEntry}', [VaultEntryController::class, 'update'])->name('vault.entries.update');
            Route::patch('/vault/entries/{vaultEntry}/pin', [VaultEntryController::class, 'togglePin'])->name('vault.entries.pin');
            Route::delete('/vault/entries/{vaultEntry}', [VaultEntryController::class, 'destroy'])->name('vault.entries.destroy');
            Route::post('/vault/entries/{vaultEntry}/secret', [VaultSecretController::class, 'reveal'])->name('vault.entries.secret');
        });

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/backup', [WorkspaceBackupController::class, 'create'])->middleware('throttle:workspace-backup')->name('settings.backup.create');
        Route::post('/settings/restore', [WorkspaceBackupController::class, 'restore'])->middleware('throttle:workspace-backup')->name('settings.backup.restore');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
});
