<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vault\StoreVaultEntryRequest;
use App\Http\Requests\Vault\ToggleVaultEntryPinRequest;
use App\Http\Requests\Vault\UpdateVaultEntryRequest;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultEntry;
use App\Support\VaultCryptography;
use App\Support\VaultEntryPayload;
use App\Support\VaultSession;
use App\Support\VaultTagSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VaultEntryController extends Controller
{
    public function __construct(
        private readonly VaultCryptography $cryptography,
        private readonly VaultEntryPayload $payloads,
        private readonly VaultSession $vaultSession,
        private readonly VaultTagSynchronizer $tags,
    ) {}

    public function store(StoreVaultEntryRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vault = $this->vaultFor($user);
        $vaultKey = $this->vaultSession->keyFor($request, $user);
        abort_if($vaultKey === null, 423);
        $validated = $request->validated();
        $tagNames = $this->tags->parse($validated['tags'] ?? null);
        $url = $this->urlFor($validated);
        $username = $this->usernameFor($validated);

        try {
            $entry = DB::transaction(function () use ($vault, $user, $vaultKey, $validated, $request, $tagNames, $url, $username): VaultEntry {
                $entry = new VaultEntry([
                    'type' => $validated['type'],
                    'title' => trim($validated['title']),
                    'url' => $url,
                    'username' => $username,
                    'secret_kind' => $validated['secret_kind'],
                    'metadata' => $request->metadata(),
                ]);
                $entry->sensitive_payload = $this->cryptography->encryptEntryPayload(
                    $vault,
                    $vaultKey,
                    $this->payloads->fromValidated($validated),
                );
                $vault->entries()->save($entry);
                $this->tags->sync($entry, $user, $tagNames);

                return $entry;
            });
        } finally {
            sodium_memzero($vaultKey);
        }

        return to_route('vault.show', $entry)->with('vault_status', 'Vault entry saved.');
    }

    public function update(UpdateVaultEntryRequest $request, VaultEntry $vaultEntry): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vault = $this->vaultFor($user);
        $vaultEntry = $this->owned($vault, $user, $vaultEntry);
        $vaultKey = $this->vaultSession->keyFor($request, $user);
        abort_if($vaultKey === null, 423);
        $validated = $request->validated();
        $expectedVersion = (int) $validated['version'];
        $tagNames = $this->tags->parse($validated['tags'] ?? null);
        $url = $this->urlFor($validated);
        $username = $this->usernameFor($validated);

        try {
            $existingPayload = $this->cryptography->decryptEntryPayload($vault, $vaultKey, $vaultEntry->sensitive_payload);
            $encryptedPayload = $this->cryptography->encryptEntryPayload(
                $vault,
                $vaultKey,
                $this->payloads->fromValidated($validated, $existingPayload),
            );
            $updated = DB::transaction(function () use ($vault, $user, $vaultEntry, $validated, $request, $encryptedPayload, $expectedVersion, $tagNames, $url, $username): bool {
                $changed = VaultEntry::query()->forVault($vault)->whereKey($vaultEntry->id)->where('lock_version', $expectedVersion)->update([
                    'type' => $validated['type'],
                    'title' => trim($validated['title']),
                    'url' => $url,
                    'username' => $username,
                    'secret_kind' => $validated['secret_kind'],
                    'metadata' => $request->metadata(),
                    'sensitive_payload' => $encryptedPayload,
                    'lock_version' => $expectedVersion + 1,
                    'updated_at' => now(),
                ]);

                if ($changed !== 1) {
                    return false;
                }

                $this->tags->sync($vaultEntry, $user, $tagNames);

                return true;
            });
        } finally {
            sodium_memzero($vaultKey);
        }

        if (! $updated) {
            return back()->withErrors(['version' => 'This Vault entry changed in another tab. Reload it before saving again.']);
        }

        return to_route('vault.show', $vaultEntry)->with('vault_status', 'Vault entry saved.');
    }

    public function togglePin(ToggleVaultEntryPinRequest $request, VaultEntry $vaultEntry): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vault = $this->vaultFor($user);
        $vaultEntry = $this->owned($vault, $user, $vaultEntry);
        $expectedVersion = (int) $request->validated('version');
        $pinned = ! $vaultEntry->is_pinned;

        $updated = VaultEntry::query()->forVault($vault)->whereKey($vaultEntry->id)->where('lock_version', $expectedVersion)->update([
            'is_pinned' => $pinned,
            'lock_version' => $expectedVersion + 1,
            'updated_at' => now(),
        ]);

        if ($updated !== 1) {
            return back()->withErrors(['version' => 'This Vault entry changed in another tab. Reload it before saving again.']);
        }

        return to_route('vault.show', $vaultEntry)->with('vault_status', $pinned ? 'Vault entry pinned.' : 'Vault entry unpinned.');
    }

    public function destroy(Request $request, VaultEntry $vaultEntry): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vault = $this->vaultFor($user);
        $vaultEntry = $this->owned($vault, $user, $vaultEntry);
        $vaultEntry->delete();

        return to_route('vault.index')->with('vault_status', 'Vault entry deleted.');
    }

    /** @param array<string, mixed> $validated */
    private function urlFor(array $validated): ?string
    {
        if ($validated['type'] === 'secure_note' || ! filled($validated['url'] ?? null)) {
            return null;
        }

        return trim((string) $validated['url']);
    }

    /** @param array<string, mixed> $validated */
    private function usernameFor(array $validated): ?string
    {
        if ($validated['type'] === 'secure_note' || ! filled($validated['username'] ?? null)) {
            return null;
        }

        return trim((string) $validated['username']);
    }

    private function vaultFor(User $user): Vault
    {
        return $user->vault ?? abort(404);
    }

    private function owned(Vault $vault, User $user, VaultEntry $vaultEntry): VaultEntry
    {
        $entry = VaultEntry::query()->forVault($vault)->whereKey($vaultEntry->id)->firstOrFail();
        Gate::authorize('view', $entry);

        return $entry;
    }
}
