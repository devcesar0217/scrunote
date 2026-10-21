<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Note;
use App\Models\Project;
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
     * Search only records owned by the current user and their existing
     * searchable projections. Sensitive Vault payloads are deliberately
     * omitted from both the SELECT and predicates.
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

        $projects = $this->projects($user, $pattern)->limit($limit)->get();
        $notes = $this->notes($user, $pattern)->limit($limit)->get();
        $documents = $this->documents($user, $pattern)->limit($limit)->get();
        $vaultEntries = $this->vaultEntries($user, $pattern)->limit($limit)->get();

        $hasMore = $projects->count() > self::RESULTS_PER_MODULE
            || $notes->count() > self::RESULTS_PER_MODULE
            || $documents->count() > self::RESULTS_PER_MODULE
            || $vaultEntries->count() > self::RESULTS_PER_MODULE;

        $results = [
            ...$projects->take(self::RESULTS_PER_MODULE)->map(fn (Project $project): array => $this->projectResult($project))->all(),
            ...$notes->take(self::RESULTS_PER_MODULE)->map(fn (Note $note): array => $this->noteResult($note))->all(),
            ...$documents->take(self::RESULTS_PER_MODULE)->map(fn (Document $document): array => $this->documentResult($document))->all(),
            ...$vaultEntries->take(self::RESULTS_PER_MODULE)->map(fn (VaultEntry $entry): array => $this->vaultResult($entry))->all(),
        ];

        return ['results' => $results, 'has_more' => $hasMore];
    }

    /** @return Builder<Project> */
    private function projects(User $user, string $pattern): Builder
    {
        return Project::query()->ownedBy($user)
            ->select(['id', 'title', 'description', 'updated_at'])
            ->where(fn (Builder $query) => $query->whereLike('title', $pattern)->orWhereLike('description', $pattern))
            ->latest('updated_at')
            ->orderByDesc('id');
    }

    /** @return Builder<Note> */
    private function notes(User $user, string $pattern): Builder
    {
        return Note::query()->ownedBy($user)->active()
            ->select(['id', 'project_id', 'title', 'content_text', 'is_pinned', 'updated_at'])
            ->with(['tags:id,name', 'project:id,title'])
            ->where(function (Builder $query) use ($user, $pattern): void {
                $query->whereLike('title', $pattern)
                    ->orWhereLike('content_text', $pattern)
                    ->orWhereHas('project', fn (Builder $project) => $project->where('user_id', $user->id)->whereLike('title', $pattern))
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
            ->select(['id', 'project_id', 'title', 'content_text', 'is_pinned', 'updated_at'])
            ->with(['tags:id,name', 'project:id,title'])
            ->where(function (Builder $query) use ($user, $pattern): void {
                $query->whereLike('title', $pattern)
                    ->orWhereLike('content_text', $pattern)
                    ->orWhereHas('project', fn (Builder $project) => $project->where('user_id', $user->id)->whereLike('title', $pattern))
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
            ->select(['id', 'vault_id', 'project_id', 'type', 'title', 'url', 'username', 'metadata', 'is_pinned', 'updated_at'])
            ->whereHas('vault', fn (Builder $vault) => $vault->where('user_id', $user->id))
            ->with([
                'project:id,title',
                'tags' => fn ($tags) => $tags->select(['tags.id', 'tags.name'])->where('user_id', $user->id)->where('module', 'vault'),
            ])
            ->where(function (Builder $query) use ($user, $pattern): void {
                $query->whereLike('title', $pattern)
                    ->orWhereLike('url', $pattern)
                    ->orWhereLike('username', $pattern)
                    ->orWhereLike('metadata', $pattern)
                    ->orWhereHas('project', fn (Builder $project) => $project->where('user_id', $user->id)->whereLike('title', $pattern))
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->where('user_id', $user->id)
                        ->where('module', 'vault')->whereLike('name', $pattern));
            })
            ->orderByDesc('is_pinned')
            ->latest('updated_at')
            ->orderByDesc('id');
    }

    /** @return array{id: string, module: string, label: string, icon: string, title: string, snippet: string, url: string} */
    private function projectResult(Project $project): array
    {
        return [
            'id' => 'project:'.$project->id,
            'module' => 'projects',
            'label' => 'Project',
            'icon' => 'folder',
            'title' => $project->title,
            'snippet' => filled($project->description) ? Str::limit($project->description, 110) : 'Project workspace',
            'url' => route('projects.show', $project),
        ];
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
            'snippet' => $this->withProject($this->snippet($note->content_text, $note->tags), $note->project),
            'url' => $note->project_id === null
                ? route('notes.show', $note)
                : route('notes.show', ['note' => $note, 'project' => $this->projectFilter($note->project_id)]),
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
            'snippet' => $this->withProject($this->snippet($document->content_text, $document->tags), $document->project),
            'url' => $document->project_id === null
                ? route('markdown.show', $document)
                : route('markdown.show', ['document' => $document, 'project' => $this->projectFilter($document->project_id)]),
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
            'snippet' => $this->withProject(Str::limit($details, 110), $entry->project),
            'url' => $entry->project_id === null
                ? route('vault.show', $entry)
                : route('vault.show', ['vaultEntry' => $entry, 'project' => $this->projectFilter($entry->project_id)]),
        ];
    }

    /** @param Collection<int, Tag> $tags */
    private function snippet(?string $content, Collection $tags): string
    {
        $text = trim((string) $content);
        $tagText = $tags->map(fn (Tag $tag): string => '#'.$tag->name)->implode(' ');

        return $text !== ''
            ? Str::limit($text, 86)
            : ($tagText !== '' ? $tagText : 'No searchable content yet');
    }

    private function withProject(string $snippet, ?Project $project): string
    {
        $location = $project?->title ?? 'My Workspace';

        return Str::limit('Project: '.$location.' · '.$snippet, 110);
    }

    private function projectFilter(int|string $projectId): int|string
    {
        return $projectId;
    }

    private function pattern(string $query): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
    }
}
