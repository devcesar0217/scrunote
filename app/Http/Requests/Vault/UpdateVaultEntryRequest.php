<?php

namespace App\Http\Requests\Vault;

class UpdateVaultEntryRequest extends VaultEntryRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'version' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<int, mixed> */
    protected function secretRules(): array
    {
        return ['nullable', 'string', 'max:20000'];
    }
}
