<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ByteaCast implements CastsAttributes
{
    /**
     * pdo_pgsql returns raw bytes for bytea columns, but right after an Eloquent create()
     * the attribute still holds the \xHEX string we wrote. Normalise both to raw bytes.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // pdo_pgsql returns bytea columns as stream resources
        if (is_resource($value)) {
            rewind($value);
            return stream_get_contents($value);
        }
        // Right after create(), the attribute still holds the \xHEX string we wrote
        if (is_string($value) && str_starts_with($value, '\\x')) {
            return hex2bin(substr($value, 2));
        }
        return $value;
    }

    /**
     * PostgreSQL's bytea input function recognises the \xHEX format, so passing it as
     * a plain PDO string parameter is enough — no DB::raw needed.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return '\\x' . bin2hex($value);
    }
}
