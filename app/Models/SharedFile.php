<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $shared_folder_id
 * @property string $name Original file name, for display and as the download name
 * @property string $storage_name Random name on the "shared" disk
 * @property int $size
 * @property-read SharedFolder $folder
 * @mixin \Eloquent
 */
class SharedFile extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['shared_folder_id', 'name', 'storage_name', 'size'];

    protected $casts = ['size' => 'int'];

    protected static function booted(): void
    {
        static::deleted(fn (SharedFile $file) => Storage::disk('shared')->delete($file->storage_name));
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(SharedFolder::class, 'shared_folder_id');
    }
}
