<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Integer stored encrypted with the app key.
 */
class EncryptedInt implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null ? null : (int) Crypt::decryptString($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString((string) (int) $value);
    }
}
