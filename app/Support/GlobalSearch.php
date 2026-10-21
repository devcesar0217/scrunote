<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use App\Models\VaultEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GlobalSearch
{
    public const MINIMUM_QUERY_LENGTH = 2;

    private const RESULTS_PER_MODULE = 8;

    /**
     * Search only the owner's existing searchable projections. Sensitive Vault
     * payloads are deliberately omitted from both the SELECT and predicates.
     *
     * @return array{results: list<array{id: string, module: string, label: string, icon: string, title: string, snippet: string, url: string}>, has_more: bool}
     */
    public function search(User $user, string $query): array
    {
        if (mb_strlen($query) < self::MINIMUM_QUERY_LENGTH) {
            return ['results' => [], 'has_more' => false];
        }

        $pattern = $this->pattern($query);
        $limit = self::RESULTS_PER_MODULE + 1;

        $notes = $this->notes($user, $pattern)->limit($limit)->get();
        $documents = $this->documents($user, $pattern)->limit($limit)->get();
        $vaultEntries = $this->vaultEntries($user, $pattern)->limit($limit)->get();

        $hasMore = $notes->count() > self::RESULTS_PER_MODULE
            || $documents->count() > self::RESULTS_PER_MODULE
            || $vaultEntries->count() > self::RESULTS_PER_MODULE;

        $results = [
            ...$notes->take(self::RESULTS_PER_MODULE)->map(fn (Note $note): array => $this->noteResult($note))->all(),
            ...$documents->take(self::RESULTS_PER_MODULE)->map(fn (Document $document): array => $this->documentResult($document))->all(),
            ...$vaultEntries->take(self::RESULTS_PER_MODULE)->map(fn (VaultEntry $entry): array => $this->vaultResult($entry))->all(),
        ];

        return ['results' => $results, 'has_more' => $hasMore];
    }

    /** @return Builder<Note> */
    private function notes(User $user, string $pattern): Builder
    {
        return Note::query()->ownedBy($user)->active()
            ->select(['id', 'title', 'content_text', 'is_pinned', 'updated_at'])
            ->with(['tags:id,name'])
            ->where(function (Builder $query) use ($user, $pattern): void {
                $query->whereLike('title', $pattern)
                    ->orWhereLike('content_text', $pattern)
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->where('user_id', $user->id)
                        ->where('module', 'notes')->whereLike('name', $pattern));
            })
            ->orderByDesc('is_pinned')
            ->latest('updated_at')
            ->orderByDesc('id');
    }

    /** @return Builder<Document> */
    private function documents(User $user, string $pattern): Builder
    {
        return Document::query()->ownedBy($user)->active()
            ->select(['id', 'title', 'content_text', 'is_pinned', 'updated_at'])
            ->with(['tags:id,name'])
            ->where(function (Builder $query) use ($user, $pattern): void {
                $query->whereLike('title', $pattern)
                    ->orWhereLike('content_text', $pattern)
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->where('user_id', $user->id)
                        ->where('module', 'markdown')->whereLike('name', $pattern));
            })
            ->orderByDesc('is_pinned')
            ->latest('updated_at')
            ->orderByDesc('id');
    }

    /** @return Builder<VaultEntry> */
    private function vaultEntries(User $user, string $pattern): Builder
    {
        return VaultEntry::query()
            // Do not select sensitive_payload. This search never decrypts Vault data.
            ->select(['id', 'vault_id', 'type', 'title', 'url', 'username', 'metadata', 'is_pinned', 'updated_at'])
            ->whereHas('vault', fn (Builder $vault) => $vault->where('user_id', $user->id))
            ->with(['tags' => fn ($tags) => $tags->select(['tags.id', 'tags.name'])->where('user_id', $user->id)->where('module', 'vault')])
            ->where(function (Builder $query) use ($user, $pattern): void {
                $query->whereLike('title', $pattern)
                    ->orWhereLike('url', $pattern)
                    ->orWhereLike('username', $pattern)
                    ->orWhereLike('metadata', $pattern)
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->where('user_id', $user->id)
                        ->where('module', 'vault')->whereLike('name', $pattern));
            })
            ->orderByDesc('is_pinned')
            ->latest('updated_at')
            ->orderByDesc('id');
    }

    /** @return array{id: string, module: string, label: string, icon: string, title: string, snippet: string, url: string} */
    private function noteResult(Note $note): array
    {
        return [
            'id' => 'note:'.$note->id,
            'module' => 'notes',
            'label' => 'Note',
            'icon' => 'notes',
            'title' => $note->display_title,
            'snippet' => $this->snippet($note->content_text, $note->tags),
            'url' => route('notes.show', $note),
        ];
    }

    /** @return array{id: string, module: string, label: string, icon: string, title: string, snippet: string, url: string} */
    private function documentResult(Document $document): array
    {
        return [
            'id' => 'document:'.$document->id,
            'module' => 'markdown',
            'label' => 'Markdown',
            'icon' => 'markdown',
            'title' => $document->display_title,
            'snippet' => $this->snippet($document->content_text, $document->tags),
            'url' => route('markdown.show', $document),
        ];
    }

    /** @return array{id: string, module: string, label: string, icon: string, title: string, snippet: string, url: string} */
    private function vaultResult(VaultEntry $entry): array
    {
        $metadata = collect($entry->metadata ?? [])
            ->filter(fn (mixed $value): bool => is_string($value) || is_int($value))
            ->map(fn (string|int $value, string $key): string => Str::headline($key).': '.$value)
            ->values();
        $details = collect([Str::headline($entry->type)])
            ->when(filled($entry->username), fn (Collection $items) => $items->push((string) $entry->username))
            ->merge($metadata)
            ->merge($entry->tags->map(fn (Tag $tag): string => '#'.$tag->name))
            ->filter()
            ->implode(' · ');

        return [
            'id' => 'vault:'.$entry->id,
            'module' => 'vault',
            'label' => 'Vault',
            'icon' => 'lock',
            'title' => $entry->title,
            'snippet' => Str::limit($details, 110),
            'url' => route('vault.show', $entry),
        ];
    }

    /** @param Collection<int, Tag> $tags */
    private function snippet(?string $content, Collection $tags): string
    {
        $text = trim((string) $content);
        $tagText = $tags->map(fn (Tag $tag): string => '#'.$tag->name)->implode(' ');

        return $text !== ''
            ? Str::limit($text, 110)
            : ($tagText !== '' ? $tagText : 'No searchable content yet');
    }

    private function pattern(string $query): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
    }
}
