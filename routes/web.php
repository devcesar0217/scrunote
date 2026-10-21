<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureUserIsActive;
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

        Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
        Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
        Route::get('/notes/{note}', [NoteController::class, 'show'])->name('notes.show');
        Route::patch('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
        Route::patch('/notes/{note}/pin', [NoteController::class, 'togglePin'])->name('notes.pin');
        Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

        // Other destinations remain shell boundaries until their own phases.
        Route::get('/markdown', WorkspaceController::class)->name('markdown.index');
        Route::get('/vault', WorkspaceController::class)->name('vault.index');
        Route::get('/settings', WorkspaceController::class)->name('settings.index');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
});
