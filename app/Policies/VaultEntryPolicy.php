<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VaultEntry;

class VaultEntryPolicy
{
    public function view(User $user, VaultEntry $vaultEntry): bool
    {
        return $vaultEntry->vault->user_id === $user->id;
    }

    public function update(User $user, VaultEntry $vaultEntry): bool
    {
        return $this->view($user, $vaultEntry);
    }

    public function delete(User $user, VaultEntry $vaultEntry): bool
    {
        return $this->view($user, $vaultEntry);
    }
}
