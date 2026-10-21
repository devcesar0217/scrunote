<?php

namespace App\Http\Requests\Vault;

class StoreVaultEntryRequest extends VaultEntryRequest
{
    /** @return array<int, mixed> */
    protected function secretRules(): array
    {
        return ['required', 'string', 'min:1', 'max:20000'];
    }
}
