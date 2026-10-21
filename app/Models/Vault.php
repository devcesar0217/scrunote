<?php

namespace App\Models;

use Database\Factories\VaultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key_salt', 'wrapped_key', 'kdf_opslimit', 'kdf_memlimit', 'encryption_version'])]
class Vault extends Model
{
    /** @use HasFactory<VaultFactory> */
    use HasFactory;

    protected $hidden = [
        'key_salt',
        'wrapped_key',
        'kdf_opslimit',
        'kdf_memlimit',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<VaultEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(VaultEntry::class);
    }

    protected function casts(): array
    {
        return [
            'kdf_opslimit' => 'integer',
            'kdf_memlimit' => 'integer',
            'encryption_version' => 'integer',
        ];
    }
}
