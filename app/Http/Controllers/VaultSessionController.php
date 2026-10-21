<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vault\SetupVaultRequest;
use App\Http\Requests\Vault\UnlockVaultRequest;
use App\Models\User;
use App\Models\Vault;
use App\Support\VaultCryptography;
use App\Support\VaultSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VaultSessionController extends Controller
{
    public function __construct(
        private readonly VaultCryptography $cryptography,
        private readonly VaultSession $vaultSession,
    ) {}

    public function setup(SetupVaultRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->vault !== null) {
            return to_route('vault.index')->with('vault_status', 'A Vault is already configured for this account.');
        }

        $masterPassword = $request->validated('master_password');
        $material = $this->cryptography->createVaultMaterial($masterPassword);

        try {
            /** @var Vault $vault */
            $vault = DB::transaction(fn (): Vault => $user->vault()->create($material));
        } catch (UniqueConstraintViolationException) {
            return to_route('vault.index')->with('vault_status', 'A Vault is already configured for this account.');
        }

        // Keep an already-resolved User instance coherent for the remainder of
        // this request (and test clients that reuse the authenticated model).
        $user->setRelation('vault', $vault);

        $vaultKey = $this->cryptography->unlockVault($vault, $masterPassword);
        try {
            $this->vaultSession->unlock($request, $vault, $vaultKey);
        } finally {
            sodium_memzero($vaultKey);
        }

        return to_route('vault.index')->with('vault_status', 'Your Secure Vault is configured and unlocked.');
    }

    public function unlock(UnlockVaultRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vault = $user->vault;
        abort_if($vault === null, 404);

        try {
            $vaultKey = $this->cryptography->unlockVault($vault, $request->validated('master_password'));
        } catch (RuntimeException) {
            return back()->withErrors(['master_password' => 'The master password could not unlock this Vault.']);
        }

        try {
            $this->vaultSession->unlock($request, $vault, $vaultKey);
        } finally {
            sodium_memzero($vaultKey);
        }

        return to_route('vault.index')->with('vault_status', 'Vault unlocked. It will lock after 5 minutes of inactivity.');
    }

    public function lock(Request $request): RedirectResponse|JsonResponse
    {
        $this->vaultSession->lock($request);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Vault locked.'], 200, ['Cache-Control' => 'no-store, private']);
        }

        return to_route('vault.index')->with('vault_status', 'Vault locked.');
    }

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->vaultSession->renew($request, $user)) {
            return response()->json(['message' => 'Vault locked.'], 423, ['Cache-Control' => 'no-store, private']);
        }

        return response()->json(['message' => 'Vault activity renewed.'], 200, ['Cache-Control' => 'no-store, private']);
    }
}
