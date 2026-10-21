<?php

namespace App\Support;

use App\Models\Vault;
use JsonException;
use RuntimeException;

class VaultCryptography
{
    private const ENCRYPTION_VERSION = 1;

    /** @return array{key_salt: string, wrapped_key: string, kdf_opslimit: int, kdf_memlimit: int, encryption_version: int} */
    public function createVaultMaterial(string $masterPassword): array
    {
        $this->assertSodiumIsAvailable();

        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $vaultKey = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $opsLimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
        $memLimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
        $wrappingKey = $this->deriveWrappingKey($masterPassword, $salt, $opsLimit, $memLimit);

        try {
            return [
                'key_salt' => base64_encode($salt),
                'wrapped_key' => $this->seal($vaultKey, $wrappingKey),
                'kdf_opslimit' => $opsLimit,
                'kdf_memlimit' => $memLimit,
                'encryption_version' => self::ENCRYPTION_VERSION,
            ];
        } finally {
            sodium_memzero($vaultKey);
            sodium_memzero($wrappingKey);
        }
    }

    public function unlockVault(Vault $vault, string $masterPassword): string
    {
        $this->assertSodiumIsAvailable();

        $salt = $this->decode($vault->key_salt);
        $wrappingKey = $this->deriveWrappingKey(
            $masterPassword,
            $salt,
            $vault->kdf_opslimit,
            $vault->kdf_memlimit,
        );

        try {
            $vaultKey = $this->open($vault->wrapped_key, $wrappingKey);
            if (strlen($vaultKey) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                sodium_memzero($vaultKey);

                throw new RuntimeException('Vault key material is invalid.');
            }

            return $vaultKey;
        } finally {
            sodium_memzero($wrappingKey);
        }
    }

    /** @param array{secret: string, notes: string} $payload */
    public function encryptEntryPayload(Vault $vault, string $vaultKey, array $payload): string
    {
        $this->assertVaultKey($vaultKey);

        try {
            $plaintext = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Vault payload could not be encoded.', previous: $exception);
        }

        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $this->entryContext($vault),
            $nonce,
            $vaultKey,
        );
        sodium_memzero($plaintext);

        return base64_encode($nonce.$ciphertext);
    }

    /** @return array{secret: string, notes: string} */
    public function decryptEntryPayload(Vault $vault, string $vaultKey, string $encryptedPayload): array
    {
        $this->assertVaultKey($vaultKey);

        $packed = $this->decode($encryptedPayload);
        $nonceSize = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($packed) <= $nonceSize) {
            throw new RuntimeException('Vault encrypted data is invalid.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($packed, $nonceSize),
            $this->entryContext($vault),
            substr($packed, 0, $nonceSize),
            $vaultKey,
        );
        if ($plaintext === false) {
            throw new RuntimeException('Vault encrypted data could not be authenticated.');
        }

        try {
            $payload = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Vault encrypted data is malformed.', previous: $exception);
        } finally {
            sodium_memzero($plaintext);
        }

        if (! is_array($payload) || ! is_string($payload['secret'] ?? null) || ! is_string($payload['notes'] ?? null)) {
            throw new RuntimeException('Vault encrypted data has an unexpected shape.');
        }

        return [
            'secret' => $payload['secret'],
            'notes' => $payload['notes'],
        ];
    }

    private function deriveWrappingKey(string $masterPassword, string $salt, int $opsLimit, int $memLimit): string
    {
        // PHP's Sodium extension does not expose libsodium's MIN constants.
        // Never accept a persisted setting below this application's approved
        // Argon2id interactive work factor.
        if ($opsLimit < SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE || $memLimit < SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE) {
            throw new RuntimeException('Vault key derivation parameters are invalid.');
        }

        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $masterPassword,
            $salt,
            $opsLimit,
            $memLimit,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }

    private function seal(string $plaintext, string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $key));
    }

    private function open(string $packedValue, string $key): string
    {
        $packed = $this->decode($packedValue);
        $nonceSize = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($packed) <= $nonceSize) {
            throw new RuntimeException('Vault key material is invalid.');
        }

        $plaintext = sodium_crypto_secretbox_open(substr($packed, $nonceSize), substr($packed, 0, $nonceSize), $key);
        if ($plaintext === false) {
            throw new RuntimeException('The master password could not unlock this Vault.');
        }

        return $plaintext;
    }

    private function entryContext(Vault $vault): string
    {
        return 'scrunote-vault-entry:v'.self::ENCRYPTION_VERSION.':'.$vault->id;
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new RuntimeException('Vault encrypted data is malformed.');
        }

        return $decoded;
    }

    private function assertVaultKey(string $vaultKey): void
    {
        $this->assertSodiumIsAvailable();
        if (strlen($vaultKey) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new RuntimeException('Vault key material is invalid.');
        }
    }

    private function assertSodiumIsAvailable(): void
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('The Sodium extension is required for Secure Vault.');
        }
    }
}
