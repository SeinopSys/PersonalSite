<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Boolean stored encrypted with the app key. A missing value reads as false.
 */
class EncryptedBool implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return $value !== null && Crypt::decryptString($value) === '1';
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return Crypt::encryptString($value ? '1' : '0');
    }
}
