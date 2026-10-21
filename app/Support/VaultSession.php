<?php

namespace App\Support;

use App\Models\User;
use App\Models\Vault;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class VaultSession
{
    private const SESSION_KEY = 'scrunote.vault';

    public function unlock(Request $request, Vault $vault, string $vaultKey): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'vault_id' => $vault->id,
            'wrapped_session_key' => Crypt::encryptString(base64_encode($vaultKey)),
            'expires_at' => now()->addMinutes($this->timeoutMinutes())->getTimestamp(),
        ]);
    }

    public function lock(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function isUnlocked(Request $request, User $user): bool
    {
        $key = $this->keyFor($request, $user);
        if ($key === null) {
            return false;
        }

        sodium_memzero($key);

        return true;
    }

    public function keyFor(Request $request, User $user): ?string
    {
        $state = $request->session()->get(self::SESSION_KEY);
        $vault = $user->vault;

        if (! is_array($state) || $vault === null || ($state['vault_id'] ?? null) !== $vault->id || $this->isExpired($state)) {
            $this->lock($request);

            return null;
        }

        try {
            $encodedKey = Crypt::decryptString((string) ($state['wrapped_session_key'] ?? ''));
            $key = base64_decode($encodedKey, true);
        } catch (Throwable) {
            $this->lock($request);

            return null;
        }

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            $this->lock($request);

            return null;
        }

        return $key;
    }

    public function renew(Request $request, User $user): bool
    {
        $key = $this->keyFor($request, $user);
        if ($key === null) {
            return false;
        }

        sodium_memzero($key);
        $state = $request->session()->get(self::SESSION_KEY);
        $state['expires_at'] = now()->addMinutes($this->timeoutMinutes())->getTimestamp();
        $request->session()->put(self::SESSION_KEY, $state);

        return true;
    }

    private function isExpired(array $state): bool
    {
        return ! is_int($state['expires_at'] ?? null) || $state['expires_at'] <= now()->getTimestamp();
    }

    private function timeoutMinutes(): int
    {
        return max(1, (int) config('vault.idle_timeout_minutes', 5));
    }
}
