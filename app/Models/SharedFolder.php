<?php

namespace App\Models;

use App\Util\BlindIndex;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string $token
 * @property string $token_index
 * @property CarbonInterface|null $expires_at Last day the link works
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SharedFile> $files
 * @mixin \Eloquent
 */
class SharedFolder extends Model
{
    use Uuids;

    public const TOKEN_LENGTH = 48;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'name', 'token', 'expires_at'];

    protected $casts = [
        'token' => 'encrypted',
        'expires_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (SharedFolder $folder) {
            $folder->token_index = BlindIndex::make($folder->token);
        });

        // Rows go with the folder through the foreign key, the stored files have to be removed explicitly
        static::deleting(function (SharedFolder $folder) {
            Storage::disk('shared')->delete($folder->files()->pluck('storage_name')->all());
        });
    }

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

    public function files(): HasMany
    {
        return $this->hasMany(SharedFile::class)->orderBy('name');
    }

    public function url(): string
    {
        return url('/s/'.$this->token);
    }
}
