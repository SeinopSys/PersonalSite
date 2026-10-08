<?php

namespace App\Models;

use App\Casts\EncryptedDate;
use App\Casts\EncryptedInt;
use App\Util\BlindIndex;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * App\Models\ShareLink
 *
 * @property string $id
 * @property string $user_id
 * @property string $token
 * @property string $token_index
 * @property string|null $label
 * @property CarbonInterface|null $expires_at Last day the link works
 * @property int|null $view_count
 * @property CarbonInterface|null $last_viewed_at
 * @property-read User $user
 * @mixin \Eloquent
 */
class ShareLink extends Model
{
    use Uuids;

    public const TOKEN_LENGTH = 48;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'token', 'label', 'expires_at', 'view_count', 'last_viewed_at'];

    // Everything stored about a link is encrypted with the app key
    protected $casts = [
        'token' => 'encrypted',
        'label' => 'encrypted',
        'expires_at' => EncryptedDate::class,
        'view_count' => EncryptedInt::class,
        'last_viewed_at' => EncryptedDate::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (ShareLink $link) {
            $link->token_index = BlindIndex::make($link->token);
        });
    }

    /** A new secret from the OS's cryptographically secure random source. */
    public static function makeToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    public static function findByToken(string $token): ?self
    {
        $index = BlindIndex::make($token);

        return $index === null ? null : static::where('token_index', $index)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->startOfDay()->gt($this->expires_at);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
