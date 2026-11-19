<?php

namespace App\Models;

use App\Util\BlindIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * App\UploadFolder
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $parent_id
 * @property string $name
 * @property bool $disable_thumbnails
 * @property bool $disable_conversion
 * @property bool $secondary_domain
 * @property string|null $share_token Secret of the share link, null while the folder isn't shared
 * @property \Carbon\CarbonInterface|null $share_expires_at Last day the share link works
 * @property-read User $user
 * @property-read UploadFolder|null $parent
 * @property-read Collection|UploadFolder[] $children
 * @property-read Collection|Upload[] $uploads
 * @property-read ImageUploadKey|null $uploadKey
 * @method static Builder|UploadFolder whereUserId($value)
 * @method static Builder|UploadFolder whereParentId($value)
 * @method static Builder|UploadFolder whereName($value)
 * @method static Builder|UploadFolder newModelQuery()
 * @method static Builder|UploadFolder newQuery()
 * @method static Builder|UploadFolder query()
 * @mixin \Eloquent
 */
class UploadFolder extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'parent_id', 'name', 'disable_thumbnails', 'disable_conversion', 'secondary_domain',
        'share_token', 'share_expires_at',
    ];

    protected $attributes = [
        'disable_thumbnails' => false,
        'disable_conversion' => false,
        'secondary_domain' => false,
    ];

    protected $casts = [
        'disable_thumbnails' => 'bool',
        'disable_conversion' => 'bool',
        'secondary_domain' => 'bool',
        'share_token' => 'encrypted',
        'share_expires_at' => 'date',
    ];

    public const SHARE_TOKEN_LENGTH = 48;

    protected static function booted(): void
    {
        static::saving(function (UploadFolder $folder) {
            $folder->share_token_index = BlindIndex::make($folder->share_token);
        });
    }

    public static function makeShareToken(): string
    {
        return \Illuminate\Support\Str::random(self::SHARE_TOKEN_LENGTH);
    }

    public static function findByShareToken(string $token): ?self
    {
        $index = BlindIndex::make($token);

        return $index === null ? null : static::where('share_token_index', $index)->first();
    }

    public function isShared(): bool
    {
        return $this->share_token !== null;
    }

    public function isShareExpired(): bool
    {
        return $this->share_expires_at !== null && now()->startOfDay()->gt($this->share_expires_at);
    }

    public function shareUrl(): ?string
    {
        return $this->share_token === null ? null : url('/s/'.$this->share_token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(UploadFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(UploadFolder::class, 'parent_id');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class, 'folder_id');
    }

    public function uploadKey(): HasOne
    {
        return $this->hasOne(ImageUploadKey::class, 'folder_id');
    }

    /**
     * Ancestor chain of this folder, root-first, for breadcrumb display. Walks an already-loaded
     * collection of the owning user's folders rather than issuing a query per level.
     */
    public function ancestors(Collection $allUserFolders): array
    {
        $byId = $allUserFolders->keyBy('id');
        $chain = [];
        $current = $byId->get($this->parent_id);
        while ($current !== null) {
            array_unshift($chain, $current);
            $current = $byId->get($current->parent_id);
        }

        return $chain;
    }

    /**
     * IDs of this folder and all of its descendants (any depth), computed in-memory from an
     * already-loaded collection of the owning user's folders.
     *
     * @return string[]
     */
    public function descendantIdsIncludingSelf(Collection $allUserFolders): array
    {
        $childrenByParent = $allUserFolders->groupBy('parent_id');

        $ids = [$this->id];
        $queue = [$this->id];
        while ($queue) {
            $parentId = array_shift($queue);
            foreach ($childrenByParent->get($parentId, []) as $child) {
                $ids[] = $child->id;
                $queue[] = $child->id;
            }
        }

        return $ids;
    }
}
