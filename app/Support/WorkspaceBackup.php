<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Note;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

/**
 * Produces self-contained, password-encrypted workspace snapshots.
 *
 * Vault entry payloads are copied as their existing ciphertext. They are never
 * decrypted during export or restore. The Vault primary key is retained because
 * legacy Vault payload associated data intentionally binds ciphertext to it.
 */
class WorkspaceBackup
{
    private const CONTEXT = 'scrunote-workspace-backup:v1';

    private const MAXIMUM_RECORDS_PER_COLLECTION = 50_000;

    public function __construct(
        private readonly RichTextSanitizer $richText,
        private readonly MarkdownTextExtractor $markdownText,
    ) {}

    public function create(User $user, string $password): string
    {
        $snapshot = $this->snapshot($user);

        try {
            $plaintext = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new WorkspaceBackupException('Your workspace could not be prepared for backup.', previous: $exception);
        }

        if (strlen($plaintext) > $this->maximumPlaintextBytes()) {
            sodium_memzero($plaintext);

            throw new WorkspaceBackupException('This workspace is too large for a browser backup. Reduce its size and try again.');
        }

        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $key = $this->deriveKey($password, $salt);

        try {
            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, self::CONTEXT, $nonce, $key);
            $envelope = json_encode([
                'format' => (string) config('backup.format'),
                'version' => (int) config('backup.version'),
                'algorithm' => 'xchacha20poly1305-ietf',
                'kdf' => [
                    'algorithm' => 'argon2id',
                    'opslimit' => $this->operationsLimit(),
                    'memlimit' => $this->memoryLimit(),
                    'salt' => base64_encode($salt),
                ],
                'nonce' => base64_encode($nonce),
                'ciphertext' => base64_encode($ciphertext),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new WorkspaceBackupException('The encrypted backup could not be created.', previous: $exception);
        } finally {
            sodium_memzero($plaintext);
            sodium_memzero($key);
        }

        return $envelope;
    }

    public function restore(User $user, string $encodedBackup, string $password): void
    {
        $snapshot = $this->decodeSnapshot($encodedBackup, $password, $user->id);

        DB::transaction(function () use ($user, $snapshot): void {
            $currentVault = $user->vault()->lockForUpdate()->first();
            $snapshotVault = $snapshot['vault'];

            // A v1 Vault ciphertext is bound to its Vault primary key. Never
            // overwrite a key already owned by somebody else just to restore a
            // foreign/corrupted file.
            if ($snapshotVault !== null
                && ($currentVault === null || $currentVault->id !== $snapshotVault['id'])
                && Vault::query()->whereKey($snapshotVault['id'])->exists()) {
                throw new WorkspaceBackupException('This backup’s Vault cannot be restored safely to this account.');
            }

            $user->notes()->delete();
            $user->documents()->delete();
            $currentVault?->delete();
            $user->tags()->delete();
            $user->projects()->delete();

            /** @var array<string, int> $tagIds */
            $tagIds = [];
            foreach ($snapshot['tags'] as $tag) {
                $created = $user->tags()->create([
                    'module' => $tag['module'],
                    'name' => $tag['name'],
                ]);
                $tagIds[$this->tagKey($tag['module'], $tag['name'])] = $created->id;
            }

            /** @var array<int, int> $projectIds */
            $projectIds = [];
            foreach ($snapshot['projects'] as $project) {
                $created = new Project;
                $created->forceFill([
                    'title' => $project['title'],
                    'description' => $project['description'],
                    'created_at' => $project['created_at'],
                    'updated_at' => $project['updated_at'],
                ]);
                $user->projects()->save($created);
                $projectIds[$project['id']] = $created->id;
            }

            foreach ($snapshot['notes'] as $note) {
                $sanitized = $this->richText->sanitize($note['content']['html']);
                $created = new Note;
                $created->forceFill([
                    'title' => $note['title'],
                    'content' => ['html' => $sanitized['html']],
                    'content_text' => $sanitized['text'],
                    'project_id' => $note['project_id'] === null ? null : $projectIds[$note['project_id']],
                    'is_favorite' => $note['is_favorite'],
                    'is_pinned' => $note['is_pinned'],
                    'archived_at' => $note['archived_at'],
                    'lock_version' => $note['lock_version'],
                    'created_at' => $note['created_at'],
                    'updated_at' => $note['updated_at'],
                ]);
                $user->notes()->save($created);
                $created->tags()->sync($this->tagIds($tagIds, 'notes', $note['tags']));
            }

            foreach ($snapshot['documents'] as $document) {
                $created = new Document;
                $created->forceFill([
                    'title' => $document['title'],
                    'markdown' => $document['markdown'],
                    'content_text' => $this->markdownText->extract($document['markdown']),
                    'project_id' => $document['project_id'] === null ? null : $projectIds[$document['project_id']],
                    'is_pinned' => $document['is_pinned'],
                    'archived_at' => $document['archived_at'],
                    'lock_version' => $document['lock_version'],
                    'created_at' => $document['created_at'],
                    'updated_at' => $document['updated_at'],
                ]);
                $user->documents()->save($created);
                $created->tags()->sync($this->tagIds($tagIds, 'markdown', $document['tags']));
            }

            if ($snapshotVault !== null) {
                Vault::query()->insert([
                    'id' => $snapshotVault['id'],
                    'user_id' => $user->id,
                    'key_salt' => $snapshotVault['key_salt'],
                    'wrapped_key' => $snapshotVault['wrapped_key'],
                    'kdf_opslimit' => $snapshotVault['kdf_opslimit'],
                    'kdf_memlimit' => $snapshotVault['kdf_memlimit'],
                    'encryption_version' => $snapshotVault['encryption_version'],
                    'created_at' => $snapshotVault['created_at'],
                    'updated_at' => $snapshotVault['updated_at'],
                ]);
                /** @var Vault $vault */
                $vault = Vault::query()->findOrFail($snapshotVault['id']);

                foreach ($snapshotVault['entries'] as $entry) {
                    $created = new VaultEntry;
                    $created->forceFill([
                        'type' => $entry['type'],
                        'title' => $entry['title'],
                        'url' => $entry['url'],
                        'username' => $entry['username'],
                        'secret_kind' => $entry['secret_kind'],
                        'metadata' => $entry['metadata'],
                        'project_id' => $entry['project_id'] === null ? null : $projectIds[$entry['project_id']],
                        'is_pinned' => $entry['is_pinned'],
                        'lock_version' => $entry['lock_version'],
                        'created_at' => $entry['created_at'],
                        'updated_at' => $entry['updated_at'],
                        'sensitive_payload' => $entry['sensitive_payload'],
                    ]);
                    $vault->entries()->save($created);
                    $created->tags()->sync($this->tagIds($tagIds, 'vault', $entry['tags']));
                }
            }
        }, 3);

        $user->unsetRelation('vault');
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        $projects = $user->projects()->orderBy('id')->get();
        $notes = $user->notes()->with('tags:id,module,name')->orderBy('id')->get();
        $documents = $user->documents()->with('tags:id,module,name')->orderBy('id')->get();
        $tags = $user->tags()->orderBy('module')->orderBy('name')->get();
        $vault = $user->vault()->with(['entries' => fn ($query) => $query->with('tags:id,module,name')->orderBy('id')])->first();

        return [
            // Schema 2 adds owner-scoped Projects. Schema 1 remains accepted
            // during restore and intentionally maps all prior records to My Workspace.
            'schema' => 2,
            // A backup is a private recovery artifact, not an import format.
            // Keep it bound to its original owner even though its contents are
            // independently encrypted with the user-chosen backup password.
            'owner_id' => $user->id,
            'created_at' => now()->toIso8601String(),
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'created_at' => $this->timestampFor($project->created_at),
                'updated_at' => $this->timestampFor($project->updated_at),
            ])->all(),
            'tags' => $tags->map(fn (Tag $tag): array => [
                'module' => $tag->module,
                'name' => $tag->name,
            ])->all(),
            'notes' => $notes->map(fn (Note $note): array => [
                'title' => $note->title,
                'project_id' => $note->project_id,
                'content' => ['html' => (string) ($note->content['html'] ?? '<p><br></p>')],
                'is_favorite' => $note->is_favorite,
                'is_pinned' => $note->is_pinned,
                'archived_at' => $this->timestampFor($note->archived_at),
                'lock_version' => $note->lock_version,
                'created_at' => $this->timestampFor($note->created_at),
                'updated_at' => $this->timestampFor($note->updated_at),
                'tags' => $note->tags->pluck('name')->values()->all(),
            ])->all(),
            'documents' => $documents->map(fn (Document $document): array => [
                'title' => $document->title,
                'project_id' => $document->project_id,
                'markdown' => $document->markdown,
                'is_pinned' => $document->is_pinned,
                'archived_at' => $this->timestampFor($document->archived_at),
                'lock_version' => $document->lock_version,
                'created_at' => $this->timestampFor($document->created_at),
                'updated_at' => $this->timestampFor($document->updated_at),
                'tags' => $document->tags->pluck('name')->values()->all(),
            ])->all(),
            'vault' => $vault === null ? null : [
                'id' => $vault->id,
                'key_salt' => $vault->key_salt,
                'wrapped_key' => $vault->wrapped_key,
                'kdf_opslimit' => $vault->kdf_opslimit,
                'kdf_memlimit' => $vault->kdf_memlimit,
                'encryption_version' => $vault->encryption_version,
                'created_at' => $this->timestampFor($vault->created_at),
                'updated_at' => $this->timestampFor($vault->updated_at),
                'entries' => $vault->entries->map(fn (VaultEntry $entry): array => [
                    'type' => $entry->type,
                    'title' => $entry->title,
                    'project_id' => $entry->project_id,
                    'url' => $entry->url,
                    'username' => $entry->username,
                    'secret_kind' => $entry->secret_kind,
                    'metadata' => $entry->metadata ?? [],
                    'sensitive_payload' => $entry->sensitive_payload,
                    'is_pinned' => $entry->is_pinned,
                    'lock_version' => $entry->lock_version,
                    'created_at' => $this->timestampFor($entry->created_at),
                    'updated_at' => $this->timestampFor($entry->updated_at),
                    'tags' => $entry->tags->pluck('name')->values()->all(),
                ])->all(),
            ],
        ];
    }

    /** @return array{tags: list<array{module: string, name: string}>, notes: list<array<string, mixed>>, documents: list<array<string, mixed>>, vault: array<string, mixed>|null} */
    private function decodeSnapshot(string $encodedBackup, string $password, int $ownerId): array
    {
        if (strlen($encodedBackup) > $this->maximumUploadBytes()) {
            throw new WorkspaceBackupException('The backup file is too large.');
        }

        try {
            $envelope = json_decode($encodedBackup, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new WorkspaceBackupException('This is not a valid Scrunote backup file.', previous: $exception);
        }

        if (! is_array($envelope)
            || ($envelope['format'] ?? null) !== config('backup.format')
            || ($envelope['version'] ?? null) !== config('backup.version')
            || ($envelope['algorithm'] ?? null) !== 'xchacha20poly1305-ietf'
            || ! is_array($envelope['kdf'] ?? null)) {
            throw new WorkspaceBackupException('This backup file has an unsupported format.');
        }

        $kdf = $envelope['kdf'];
        if (($kdf['algorithm'] ?? null) !== 'argon2id'
            || ($kdf['opslimit'] ?? null) !== $this->operationsLimit()
            || ($kdf['memlimit'] ?? null) !== $this->memoryLimit()) {
            throw new WorkspaceBackupException('This backup uses unsupported security parameters.');
        }

        $salt = $this->base64($kdf['salt'] ?? null, SODIUM_CRYPTO_PWHASH_SALTBYTES, 'backup salt');
        $nonce = $this->base64($envelope['nonce'] ?? null, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES, 'backup nonce');
        $ciphertext = $this->base64($envelope['ciphertext'] ?? null, null, 'backup ciphertext');
        if (strlen($ciphertext) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new WorkspaceBackupException('This backup file is incomplete.');
        }

        $key = $this->deriveKey($password, $salt);
        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, self::CONTEXT, $nonce, $key);
        } finally {
            sodium_memzero($key);
        }
        if ($plaintext === false) {
            throw new WorkspaceBackupException('The backup password is incorrect or this backup was changed.');
        }

        try {
            $snapshot = json_decode($plaintext, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new WorkspaceBackupException('The decrypted backup data is invalid.', previous: $exception);
        } finally {
            sodium_memzero($plaintext);
        }

        return $this->validateSnapshot($snapshot, $ownerId);
    }

    /** @return array{tags: list<array{module: string, name: string}>, notes: list<array<string, mixed>>, documents: list<array<string, mixed>>, vault: array<string, mixed>|null} */
    private function validateSnapshot(mixed $snapshot, int $ownerId): array
    {
        $schema = is_array($snapshot) ? ($snapshot['schema'] ?? null) : null;
        if (! is_array($snapshot) || ! in_array($schema, [1, 2], true)) {
            throw new WorkspaceBackupException('This backup contains an unsupported workspace snapshot.');
        }

        // Eloquent's normal integer primary-key cast produces an integer here,
        // but accept the canonical decimal JSON string too. This keeps backups
        // made by supported MySQL/PDO configurations portable without allowing
        // a backup from another account to be imported.
        $snapshotOwnerId = $snapshot['owner_id'] ?? null;
        if (is_string($snapshotOwnerId) && ctype_digit($snapshotOwnerId)) {
            $snapshotOwnerId = (int) $snapshotOwnerId;
        }
        if (! is_int($snapshotOwnerId) || $snapshotOwnerId !== $ownerId) {
            throw new WorkspaceBackupException('This backup belongs to a different Scrunote account.');
        }

        $projects = $schema === 2 ? $this->list($snapshot, 'projects') : [];
        /** @var array<int, true> $projectIds */
        $projectIds = [];
        $normalizedProjects = [];
        foreach ($projects as $project) {
            $normalized = $this->project($project);
            if (isset($projectIds[$normalized['id']])) {
                $this->invalid();
            }
            $projectIds[$normalized['id']] = true;
            $normalizedProjects[] = $normalized;
        }
        $projectTitles = [];
        foreach ($normalizedProjects as $project) {
            $titleKey = mb_strtolower(trim($project['title']));
            if (isset($projectTitles[$titleKey])) {
                $this->invalid();
            }
            $projectTitles[$titleKey] = true;
        }

        $tags = $this->list($snapshot, 'tags');
        $notes = $this->list($snapshot, 'notes');
        $documents = $this->list($snapshot, 'documents');
        $tagKeys = [];
        $normalizedTags = [];
        foreach ($tags as $tag) {
            if (! is_array($tag)) {
                $this->invalid();
            }
            $module = $tag['module'] ?? null;
            if (! is_string($module) || ! in_array($module, ['notes', 'markdown', 'vault'], true)) {
                $this->invalid();
            }
            $name = $this->tagName($tag['name'] ?? null);
            $key = $this->tagKey($module, $name);
            if (isset($tagKeys[$key])) {
                $this->invalid();
            }
            $tagKeys[$key] = true;
            $normalizedTags[] = ['module' => $module, 'name' => $name];
        }

        $normalizedNotes = array_map(fn (mixed $note): array => $this->note($note, $tagKeys, $projectIds, $schema === 2), $notes);
        $normalizedDocuments = array_map(fn (mixed $document): array => $this->document($document, $tagKeys, $projectIds, $schema === 2), $documents);
        $normalizedVault = $this->vault($snapshot['vault'] ?? null, $tagKeys, $projectIds, $schema === 2);

        return [
            'projects' => $normalizedProjects,
            'tags' => $normalizedTags,
            'notes' => $normalizedNotes,
            'documents' => $normalizedDocuments,
            'vault' => $normalizedVault,
        ];
    }

    /** @return array<string, mixed> */
    private function project(mixed $project): array
    {
        if (! is_array($project)) {
            $this->invalid();
        }
        $id = $this->projectIdentifier($project['id'] ?? null);

        return [
            'id' => $id,
            'title' => $this->string($project['title'] ?? null, 120, false),
            'description' => $this->nullableString($project['description'] ?? null, 2000),
            'created_at' => $this->timestamp($project['created_at'] ?? null),
            'updated_at' => $this->timestamp($project['updated_at'] ?? null),
        ];
    }

    /** @param array<string, bool> $projectIds */
    private function note(mixed $note, array $tagKeys, array $projectIds, bool $hasProjects): array
    {
        if (! is_array($note) || ! is_array($note['content'] ?? null)) {
            $this->invalid();
        }
        $html = $this->string($note['content']['html'] ?? null, 250_000, false);

        return [
            'title' => $this->nullableString($note['title'] ?? null, 255),
            'project_id' => $this->projectReference($note, $projectIds, $hasProjects),
            'content' => ['html' => $html],
            'is_favorite' => $this->boolean($note['is_favorite'] ?? null),
            'is_pinned' => $this->boolean($note['is_pinned'] ?? null),
            'archived_at' => $this->nullableTimestamp($note['archived_at'] ?? null),
            'lock_version' => $this->version($note['lock_version'] ?? null),
            'created_at' => $this->timestamp($note['created_at'] ?? null),
            'updated_at' => $this->timestamp($note['updated_at'] ?? null),
            'tags' => $this->tagsFor('notes', $note['tags'] ?? null, $tagKeys),
        ];
    }

    /** @return array<string, mixed> */
    private function document(mixed $document, array $tagKeys, array $projectIds, bool $hasProjects): array
    {
        if (! is_array($document)) {
            $this->invalid();
        }

        return [
            'title' => $this->nullableString($document['title'] ?? null, 255),
            'project_id' => $this->projectReference($document, $projectIds, $hasProjects),
            'markdown' => $this->string($document['markdown'] ?? null, 2_000_000, true),
            'is_pinned' => $this->boolean($document['is_pinned'] ?? null),
            'archived_at' => $this->nullableTimestamp($document['archived_at'] ?? null),
            'lock_version' => $this->version($document['lock_version'] ?? null),
            'created_at' => $this->timestamp($document['created_at'] ?? null),
            'updated_at' => $this->timestamp($document['updated_at'] ?? null),
            'tags' => $this->tagsFor('markdown', $document['tags'] ?? null, $tagKeys),
        ];
    }

    /** @return array<string, mixed>|null */
    private function vault(mixed $vault, array $tagKeys, array $projectIds, bool $hasProjects): ?array
    {
        if ($vault === null) {
            return null;
        }
        if (! is_array($vault)) {
            $this->invalid();
        }
        $id = $vault['id'] ?? null;
        if (! is_int($id) || $id < 1) {
            $this->invalid();
        }
        $keySalt = $this->base64($vault['key_salt'] ?? null, SODIUM_CRYPTO_PWHASH_SALTBYTES, 'Vault key salt');
        // Re-encode canonical data after validation to avoid ambiguous base64 strings.
        $keySalt = base64_encode($keySalt);
        $wrappedKey = $this->string($vault['wrapped_key'] ?? null, 50_000, false);
        $ops = $vault['kdf_opslimit'] ?? null;
        $memory = $vault['kdf_memlimit'] ?? null;
        if (! is_int($ops) || ! is_int($memory)
            || $ops < SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE
            || $memory < SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
            || ($vault['encryption_version'] ?? null) !== 1) {
            $this->invalid();
        }

        $entries = $this->list($vault, 'entries');
        $normalizedEntries = array_map(fn (mixed $entry): array => $this->entry($entry, $tagKeys, $projectIds, $hasProjects), $entries);

        return [
            'id' => $id,
            'key_salt' => $keySalt,
            'wrapped_key' => $wrappedKey,
            'kdf_opslimit' => $ops,
            'kdf_memlimit' => $memory,
            'encryption_version' => 1,
            'created_at' => $this->timestamp($vault['created_at'] ?? null),
            'updated_at' => $this->timestamp($vault['updated_at'] ?? null),
            'entries' => $normalizedEntries,
        ];
    }

    /** @return array<string, mixed> */
    private function entry(mixed $entry, array $tagKeys, array $projectIds, bool $hasProjects): array
    {
        if (! is_array($entry)) {
            $this->invalid();
        }
        $type = $entry['type'] ?? null;
        $secretKind = $entry['secret_kind'] ?? null;
        if (! is_string($type) || ! in_array($type, VaultEntry::TYPES, true)
            || ! is_string($secretKind) || ! in_array($secretKind, VaultEntry::SECRET_KINDS, true)
            || ! $this->validSecretKind($type, $secretKind)) {
            $this->invalid();
        }
        $url = $this->nullableString($entry['url'] ?? null, 2048);
        if ($url !== null && (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $this->invalid();
        }
        $username = $this->nullableString($entry['username'] ?? null, 320);
        $metadata = $this->metadata($type, $entry['metadata'] ?? null);
        if ($type === 'secure_note') {
            $url = null;
            $username = null;
            $metadata = [];
        }

        return [
            'type' => $type,
            'title' => $this->string($entry['title'] ?? null, 160, false),
            'project_id' => $this->projectReference($entry, $projectIds, $hasProjects),
            'url' => $url,
            'username' => $username,
            'secret_kind' => $secretKind,
            'metadata' => $metadata,
            'sensitive_payload' => $this->encryptedPayload($entry['sensitive_payload'] ?? null),
            'is_pinned' => $this->boolean($entry['is_pinned'] ?? null),
            'lock_version' => $this->version($entry['lock_version'] ?? null),
            'created_at' => $this->timestamp($entry['created_at'] ?? null),
            'updated_at' => $this->timestamp($entry['updated_at'] ?? null),
            'tags' => $this->tagsFor('vault', $entry['tags'] ?? null, $tagKeys),
        ];
    }

    private function projectIdentifier(mixed $value): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }
        if (! is_int($value) || $value < 1) {
            $this->invalid();
        }

        return $value;
    }

    /** @param array<string, mixed> $record @param array<int, true> $projectIds */
    private function projectReference(array $record, array $projectIds, bool $hasProjects): ?int
    {
        if (! $hasProjects) {
            return null;
        }
        if (! array_key_exists('project_id', $record) || $record['project_id'] === null) {
            if (array_key_exists('project_id', $record)) {
                return null;
            }
            $this->invalid();
        }
        $id = $this->projectIdentifier($record['project_id']);
        if (! isset($projectIds[$id])) {
            $this->invalid();
        }

        return $id;
    }

    /** @return array<string, string|int> */
    private function metadata(string $type, mixed $metadata): array
    {
        if ($metadata === null) {
            return [];
        }
        if (! is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            $this->invalid();
        }
        $allowed = match ($type) {
            'server' => ['host', 'port', 'protocol'],
            'database' => ['host', 'port', 'database', 'engine'],
            'api' => ['environment'],
            default => [],
        };
        if (array_diff(array_keys($metadata), $allowed) !== []) {
            $this->invalid();
        }

        $normalized = [];
        foreach ($metadata as $key => $value) {
            if ($key === 'port') {
                // HTML form values are strings. Laravel's integer validator
                // deliberately validates rather than casts, so an otherwise
                // valid Vault Server/Database entry can persist "443" in its
                // JSON metadata. Accept only canonical decimal strings and
                // normalize them before restoring.
                if (is_string($value) && ctype_digit($value)) {
                    $value = (int) $value;
                }
                if (! is_int($value) || $value < 1 || $value > 65535) {
                    $this->invalid();
                }
                $normalized[$key] = $value;

                continue;
            }
            $maximum = $key === 'environment' ? 80 : 255;
            $string = $this->string($value, $maximum, false);
            if (($key === 'protocol' && ! in_array($string, ['ssh', 'sftp', 'ftp', 'rdp'], true))
                || ($key === 'engine' && ! in_array($string, ['mysql', 'postgresql', 'mariadb', 'sql_server', 'sqlite'], true))) {
                $this->invalid();
            }
            $normalized[$key] = $string;
        }

        return $normalized;
    }

    /** @param array<string, bool> $tagKeys @return list<string> */
    private function tagsFor(string $module, mixed $tags, array $tagKeys): array
    {
        if (! is_array($tags) || ! array_is_list($tags) || count($tags) > 12) {
            $this->invalid();
        }
        $normalized = [];
        foreach ($tags as $tag) {
            $name = $this->tagName($tag);
            if (! isset($tagKeys[$this->tagKey($module, $name)])) {
                $this->invalid();
            }
            $normalized[] = $name;
        }

        if (count(array_unique($normalized)) !== count($normalized)) {
            $this->invalid();
        }

        return $normalized;
    }

    /** @param array<string, int> $tagIds @param list<string> $names @return list<int> */
    private function tagIds(array $tagIds, string $module, array $names): array
    {
        return array_map(fn (string $name): int => $tagIds[$this->tagKey($module, $name)], $names);
    }

    private function validSecretKind(string $type, string $secretKind): bool
    {
        return match ($type) {
            'secure_note' => $secretKind === 'secure_note',
            'api' => in_array($secretKind, ['api_key', 'token'], true),
            default => $secretKind === 'password',
        };
    }

    private function encryptedPayload(mixed $payload): string
    {
        $value = $this->string($payload, 2_000_000, false);
        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            $this->invalid();
        }

        return $value;
    }

    /** @return list<mixed> */
    private function list(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (! is_array($value) || ! array_is_list($value) || count($value) > self::MAXIMUM_RECORDS_PER_COLLECTION) {
            $this->invalid();
        }

        return $value;
    }

    private function string(mixed $value, int $maximum, bool $allowEmpty): string
    {
        if (! is_string($value) || mb_strlen($value) > $maximum || (! $allowEmpty && trim($value) === '') || str_contains($value, "\0")) {
            $this->invalid();
        }

        return $value;
    }

    private function nullableString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->string($value, $maximum, true);
    }

    private function boolean(mixed $value): bool
    {
        if (! is_bool($value)) {
            $this->invalid();
        }

        return $value;
    }

    private function version(mixed $value): int
    {
        if (! is_int($value) || $value < 0 || $value > 4_294_967_295) {
            $this->invalid();
        }

        return $value;
    }

    private function timestamp(mixed $value): string
    {
        if (! is_string($value) || strlen($value) > 40) {
            $this->invalid();
        }

        try {
            return CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s');
        } catch (Throwable) {
            $this->invalid();
        }
    }

    private function nullableTimestamp(mixed $value): ?string
    {
        return $value === null ? null : $this->timestamp($value);
    }

    private function timestampFor(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toIso8601String();
    }

    private function tagName(mixed $value): string
    {
        $name = $this->string($value, 50, false);
        if (! preg_match('/^[\pL\pN][\pL\pN\s#._:\/+-]*$/u', $name)) {
            $this->invalid();
        }

        return $name;
    }

    private function tagKey(string $module, string $name): string
    {
        return $module."\0".$name;
    }

    private function base64(mixed $value, ?int $expectedLength, string $label): string
    {
        if (! is_string($value)) {
            throw new WorkspaceBackupException('This backup has an invalid '.$label.'.');
        }
        $decoded = base64_decode($value, true);
        if ($decoded === false || ($expectedLength !== null && strlen($decoded) !== $expectedLength)) {
            throw new WorkspaceBackupException('This backup has an invalid '.$label.'.');
        }

        return $decoded;
    }

    private function deriveKey(string $password, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            $password,
            $salt,
            $this->operationsLimit(),
            $this->memoryLimit(),
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }

    private function operationsLimit(): int
    {
        return (int) config('backup.kdf.opslimit');
    }

    private function memoryLimit(): int
    {
        return (int) config('backup.kdf.memlimit');
    }

    private function maximumUploadBytes(): int
    {
        return (int) config('backup.maximum_upload_kilobytes') * 1024;
    }

    private function maximumPlaintextBytes(): int
    {
        return (int) config('backup.maximum_plaintext_bytes');
    }

    private function invalid(): never
    {
        throw new WorkspaceBackupException('The decrypted backup data is invalid.');
    }
}
