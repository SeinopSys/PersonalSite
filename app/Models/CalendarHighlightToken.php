<?php

namespace App\Models;

use App\Casts\ByteaCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarHighlightToken extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'token', 'label', 'archived', 'created_at', 'updated_at'];

    protected $casts = ['token' => ByteaCast::class, 'archived' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function words(): HasMany
    {
        return $this->hasMany(CalendarHighlightWord::class, 'token_id');
    }

    /** Base64url-encoded token for display and API use. */
    public function getTokenBase64Attribute(): string
    {
        return rtrim(strtr(base64_encode($this->token), '+/', '-_'), '=');
    }

    /** Generate a cryptographically-random token whose base64url form contains no - or _ characters. */
    public static function generateToken(): string
    {
        do {
            $bytes = random_bytes(32);
            $encoded = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        } while (strpbrk($encoded, '-_') !== false);

        return $bytes;
    }

    /** Validate a base64url string (43 chars, 256-bit token). */
    public static function isValidBase64Url(string $input): bool
    {
        return strlen($input) === 43 && preg_match('/^[A-Za-z0-9_-]+$/', $input) === 1;
    }

    /** Decode a base64url string to raw bytes, or null if malformed. */
    public static function decodeBase64Url(string $input): ?string
    {
        if (!self::isValidBase64Url($input)) {
            return null;
        }
        $padded = strtr($input, '-_', '+/') . '=';
        $bytes = base64_decode($padded, true);
        return ($bytes !== false && strlen($bytes) === 32) ? $bytes : null;
    }

    /** Look up a token by its base64url representation for a specific user. */
    public static function findByBase64Url(string $base64url, string $userId): ?self
    {
        $bytes = self::decodeBase64Url($base64url);
        if ($bytes === null) {
            return null;
        }
        // Use decode(hex,'hex') so PostgreSQL receives a typed bytea comparison.
        return static::whereRaw("token = decode(?, 'hex')", [bin2hex($bytes)])
            ->where('user_id', $userId)
            ->with('words')
            ->first();
    }
}
