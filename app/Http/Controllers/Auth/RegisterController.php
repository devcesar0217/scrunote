<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        abort_unless(config('auth.registration_enabled'), 404);

        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        try {
            $user = User::create($request->safe()->only([
                'first_name', 'last_name', 'username', 'email', 'password',
            ]));
        } catch (UniqueConstraintViolationException) {
            // Handle a concurrent signup that passed validation before another insert.
            throw ValidationException::withMessages([
                'email' => 'That email address or username is already registered.',
            ]);
        }

        event(new Registered($user));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
