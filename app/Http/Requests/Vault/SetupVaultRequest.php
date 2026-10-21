<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetupVaultRequest extends FormRequest
{
    protected $dontFlash = ['master_password', 'master_password_confirmation'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'master_password' => ['bail', 'required', 'string', Password::min(12), 'max:1024', 'not_regex:/\x00/', 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'master_password.confirmed' => 'Confirm the Vault master password exactly.',
            'master_password.not_regex' => 'The master password contains an unsupported character.',
        ];
    }
}
