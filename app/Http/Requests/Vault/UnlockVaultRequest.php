<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class UnlockVaultRequest extends FormRequest
{
    protected $dontFlash = ['master_password'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'master_password' => ['required', 'string', 'max:1024', 'not_regex:/\x00/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'master_password.not_regex' => 'The master password contains an unsupported character.',
        ];
    }
}
