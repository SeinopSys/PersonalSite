<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Keyed hash of a value, so encrypted columns can still be checked for duplicates without storing the plaintext.
 * Derived from APP_KEY, so rotating the key invalidates it just like it does the encrypted data itself.
 */
class BlindIndex
{
    public static function make(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, 'blind-index|'.config('app.key'));
    }
}
