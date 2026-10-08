<?php

namespace App\Casts;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Calendar date (Y-m-d) stored encrypted with the app key.
 */
class EncryptedDate implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonInterface
    {
        return $value === null ? null : Carbon::createFromFormat('!Y-m-d', Crypt::decryptString($value));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        $date = $value instanceof CarbonInterface ? $value : Carbon::parse($value);

        return Crypt::encryptString($date->format('Y-m-d'));
    }
}
