<?php

namespace Database\Factories;

use App\Models\Vault;
use App\Models\VaultEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VaultEntry> */
class VaultEntryFactory extends Factory
{
    protected $model = VaultEntry::class;

    public function definition(): array
    {
        return [
            'vault_id' => Vault::factory(),
            'type' => 'login',
            'title' => fake()->company().' account',
            'url' => fake()->url(),
            'username' => fake()->safeEmail(),
            'secret_kind' => 'password',
            'metadata' => [],
            'sensitive_payload' => base64_encode(random_bytes(128)),
            'is_pinned' => false,
            'lock_version' => 0,
        ];
    }
}
