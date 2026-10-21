<?php

namespace App\Models;

use Database\Factories\VaultEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['type', 'title', 'url', 'username', 'secret_kind', 'metadata', 'is_pinned'])]
class VaultEntry extends Model
{
    public const TYPES = ['login', 'server', 'database', 'api', 'secure_note'];

    public const SECRET_KINDS = ['password', 'api_key', 'token', 'secure_note'];

    /** @use HasFactory<VaultEntryFactory> */
    use HasFactory;

    protected $hidden = ['sensitive_payload'];

    /** @return BelongsTo<Vault, $this> */
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'vault_entry_tags');
    }

    /** @param Builder<VaultEntry> $query @return Builder<VaultEntry> */
    public function scopeForVault(Builder $query, Vault $vault): Builder
    {
        return $query->whereBelongsTo($vault);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_pinned' => 'boolean',
            'lock_version' => 'integer',
        ];
    }
}
