<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RestoreWorkspaceBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'backup_file' => ['bail', 'required', 'file', 'max:'.config('backup.maximum_upload_kilobytes')],
            'backup_password' => ['bail', 'required', 'string', Password::min(12), 'max:1024', 'not_regex:/\x00/'],
            'replace_workspace' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'backup_file.max' => 'The backup file may not be larger than 20 MB.',
            'backup_password.not_regex' => 'The backup password contains an unsupported character.',
            'replace_workspace.accepted' => 'Confirm that restoring will replace your current workspace.',
        ];
    }
}
