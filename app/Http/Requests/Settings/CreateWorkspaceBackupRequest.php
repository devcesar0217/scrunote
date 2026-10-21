<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateWorkspaceBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'backup_password' => ['bail', 'required', 'string', Password::min(12), 'max:1024', 'not_regex:/\x00/', 'confirmed'],
            'backup_acknowledgement' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'backup_password.confirmed' => 'Confirm the backup password exactly.',
            'backup_password.not_regex' => 'The backup password contains an unsupported character.',
            'backup_acknowledgement.accepted' => 'Confirm that you will protect this encrypted backup and its password separately.',
        ];
    }
}
