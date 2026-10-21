<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VaultEntry;
use App\Support\VaultCryptography;
use App\Support\VaultSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VaultSecretController extends Controller
{
    public function __construct(
        private readonly VaultCryptography $cryptography,
        private readonly VaultSession $vaultSession,
    ) {}

    public function reveal(Request $request, VaultEntry $vaultEntry): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vault = $user->vault;
        abort_if($vault === null, 404);
        $vaultEntry = VaultEntry::query()->forVault($vault)->whereKey($vaultEntry->id)->firstOrFail();
        Gate::authorize('view', $vaultEntry);
        $vaultKey = $this->vaultSession->keyFor($request, $user);
        abort_if($vaultKey === null, 423);

        try {
            $payload = $this->cryptography->decryptEntryPayload($vault, $vaultKey, $vaultEntry->sensitive_payload);
        } finally {
            sodium_memzero($vaultKey);
        }

        return response()->json($payload, 200, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
