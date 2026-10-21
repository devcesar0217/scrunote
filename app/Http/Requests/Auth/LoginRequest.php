<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('login'))) {
            $this->merge(['login' => Str::lower(trim($this->input('login')))]);
        }
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'login' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($this->throttleKey()).' seconds.',
            ]);
        }

        $field = str_contains($this->string('login')->toString(), '@') ? 'email' : 'username';

        if (! Auth::attempt([
            $field => $this->validated('login'),
            'password' => $this->validated('password'),
            'is_active' => true,
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'login' => 'The supplied credentials could not be verified.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        // Do not store email addresses or usernames in rate-limit cache keys.
        return 'login:'.hash('sha256', $this->string('login')->toString().'|'.$this->ip());
    }
}
