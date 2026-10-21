<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/** Prevent bcrypt from silently ignoring bytes after its 72-byte limit. */
class PasswordByteLimit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && Hash::getDefaultDriver() === 'bcrypt' && strlen($value) > 72) {
            $fail('Use a password of at most 72 bytes; some characters use more than one byte.');
        }
    }
}
