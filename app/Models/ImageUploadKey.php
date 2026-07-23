<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\ImageUploadKey
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $folder_id
 * @property string $upload_key
 * @property-read User $user
 * @property-read UploadFolder|null $folder
 * @method static Builder|ImageUploadKey whereUserId($value)
 * @method static Builder|ImageUploadKey whereFolderId($value)
 * @method static Builder|ImageUploadKey whereUploadKey($value)
 * @method static Builder|ImageUploadKey newModelQuery()
 * @method static Builder|ImageUploadKey newQuery()
 * @method static Builder|ImageUploadKey query()
 * @mixin \Eloquent
 */
class ImageUploadKey extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'folder_id', 'upload_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(UploadFolder::class, 'folder_id');
    }

    public function generateUploadKey(): void
    {
        $this->upload_key = rtrim(base64_encode(random_bytes(40)), '=');
    }

    public function isRoot(): bool
    {
        return $this->folder_id === null;
    }
}
