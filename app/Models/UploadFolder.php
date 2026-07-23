<?php

namespace App\Models;

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
    ];

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
