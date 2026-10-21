<?php

namespace App\Support;

use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NoteTagSynchronizer
{
    /** @return list<string> */
    public function parse(?string $raw): array
    {
        $tags = collect(explode(',', (string) $raw))
            ->map(fn (string $tag): string => trim(ltrim($tag, '#')))
            ->filter()
            ->map(fn (string $tag): string => preg_replace('/\s+/u', ' ', $tag) ?? '')
            ->map(fn (string $tag): string => Str::lower($tag))
            ->unique()
            ->values();

        if ($tags->count() > 12 || $tags->contains(fn (string $tag): bool => mb_strlen($tag) > 50 || ! preg_match('/^[\pL\pN][\pL\pN\s#._:\/+-]*$/u', $tag))) {
            throw ValidationException::withMessages([
                'tags' => 'Use up to 12 tags. Each tag must start with a letter or number and be 50 characters or fewer.',
            ]);
        }

        return $tags->all();
    }

    /** @param list<string> $names */
    public function sync(Note $note, User $user, array $names): void
    {
        $ids = collect($names)->map(function (string $name) use ($user): int {
            try {
                return Tag::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'module' => 'notes',
                    'name' => $name,
                ])->id;
            } catch (UniqueConstraintViolationException) {
                // Another tab/request created the same user-scoped tag first.
                return Tag::query()->where([
                    'user_id' => $user->id,
                    'module' => 'notes',
                    'name' => $name,
                ])->firstOrFail()->id;
            }
        })->all();

        $note->tags()->sync($ids);
    }
}
