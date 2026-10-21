<?php

namespace App\Support;

class VaultEntryPayload
{
    /** @param array<string, mixed> $validated @param array{secret: string, notes: string}|null $existing @return array{secret: string, notes: string} */
    public function fromValidated(array $validated, ?array $existing = null): array
    {
        $secret = $validated['secret'] ?? null;
        if (! is_string($secret) || $secret === '') {
            $secret = $existing['secret'] ?? '';
        }

        $notes = $existing['notes'] ?? '';
        if (($validated['clear_notes'] ?? false) === true) {
            $notes = '';
        } elseif (isset($validated['notes']) && $validated['notes'] !== '') {
            $notes = $validated['notes'];
        }

        return [
            'secret' => $secret,
            'notes' => $notes,
        ];
    }
}
