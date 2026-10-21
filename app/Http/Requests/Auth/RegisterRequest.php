<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\PasswordByteLimit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('auth.registration_enabled');
    }

    protected function prepareForValidation(): void
    {
        foreach (['email', 'username'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::lower(trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/', Rule::unique(User::class)],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique(User::class)],
            'password' => ['bail', 'required', 'string', Password::min(12), 'max:1024', 'not_regex:/\x00/', 'confirmed', new PasswordByteLimit],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Use letters, numbers, dots, underscores or hyphens, starting with a letter or number.',
            'password.not_regex' => 'The password contains an unsupported character.',
        ];
    }
}
