<?php

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property array{html?: string}|null $content
 */
#[Fillable(['title', 'content', 'content_text', 'project_id', 'is_pinned', 'is_favorite', 'archived_at'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_favorite' => 'boolean',
            'is_pinned' => 'boolean',
            'archived_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'note_tags');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @param Builder<Note> $query @return Builder<Note> */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /** @param Builder<Note> $query @return Builder<Note> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    protected function displayTitle(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->title) ? $this->title : 'Untitled note');
    }

    public function html(): string
    {
        return $this->content['html'] ?? '<p><br></p>';
    }
}
