<?php

namespace App\Http\Requests\Vault;

use App\Models\VaultEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class VaultEntryRequest extends FormRequest
{
    protected $dontFlash = ['secret', 'notes'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string', Rule::in(VaultEntry::TYPES)],
            'url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'username' => ['nullable', 'string', 'max:320'],
            'secret_kind' => ['required', 'string', Rule::in(VaultEntry::SECRET_KINDS)],
            'secret' => $this->secretRules(),
            'notes' => ['nullable', 'string', 'max:20000'],
            'clear_notes' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array:host,port,protocol,database,engine,environment'],
            'metadata.host' => ['nullable', 'string', 'max:255'],
            'metadata.port' => ['nullable', 'integer', 'between:1,65535'],
            'metadata.protocol' => ['nullable', 'string', Rule::in(['ssh', 'sftp', 'ftp', 'rdp'])],
            'metadata.database' => ['nullable', 'string', 'max:255'],
            'metadata.engine' => ['nullable', 'string', Rule::in(['mysql', 'postgresql', 'mariadb', 'sql_server', 'sqlite'])],
            'metadata.environment' => ['nullable', 'string', 'max:80'],
            'tags' => ['nullable', 'string', 'max:650'],
        ];
    }

    /** @return array<int, mixed> */
    abstract protected function secretRules(): array;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $secretKind = $this->input('secret_kind');

            if ($type === 'secure_note' && $secretKind !== 'secure_note') {
                $validator->errors()->add('secret_kind', 'Secure Notes must use the Secure Note secret type.');
            }

            if ($type === 'api' && ! in_array($secretKind, ['api_key', 'token'], true)) {
                $validator->errors()->add('secret_kind', 'API Credentials must use an API key or token secret type.');
            }

            if (in_array($type, ['login', 'server', 'database'], true) && $secretKind !== 'password') {
                $validator->errors()->add('secret_kind', 'Login, Server, and Database entries must use a password secret type.');
            }
        });
    }

    /** @return array<string, string|int> */
    public function metadata(): array
    {
        $type = $this->validated('type');
        $allowed = match ($type) {
            'server' => ['host', 'port', 'protocol'],
            'database' => ['host', 'port', 'database', 'engine'],
            'api' => ['environment'],
            default => [],
        };

        return array_filter(
            Arr::only($this->validated('metadata', []), $allowed),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }
}
