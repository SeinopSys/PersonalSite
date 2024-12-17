<?php

namespace App\Models;

use App\Http\Controllers\UploadsController;
use App\Util\Core;
use App\Util\UploadUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * App\Upload
 *
 * @property string $id
 * @property string $uploaded_by
 * @property string $orig_filename
 * @property string $filename
 * @property string $extension
 * @property string $mimetype
 * @property int $size
 * @property int $additional_size
 * @property string|null $uploaded_at
 * @property int|null $width
 * @property int|null $height
 * @property bool $secondary_domain
 * @property-read User $uploader
 * @property-read string $host
 * @property-read string $total_size
 * @method static Builder|Upload whereExtension($value)
 * @method static Builder|Upload whereFilename($value)
 * @method static Builder|Upload whereHeight($value)
 * @method static Builder|Upload whereId($value)
 * @method static Builder|Upload whereMimetype($value)
 * @method static Builder|Upload whereOrigFilename($value)
 * @method static Builder|Upload whereSize($value)
 * @method static Builder|Upload whereUploadedAt($value)
 * @method static Builder|Upload whereUploadedBy($value)
 * @method static Builder|Upload whereWidth($value)
 * @method static Builder|Upload newModelQuery()
 * @method static Builder|Upload newQuery()
 * @method static Builder|Upload query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Upload whereSecondaryDomain($value)
 * @method static Builder|Upload whereAdditionalSize($value)
 * @mixin \Eloquent
 */
class Upload extends Model
{
    use Uuids;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Indicates if the table uses timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uploader', 'orig_filename', 'filename', 'mimetype', 'size', 'uploaded_at',
    ];

    /**
     * Get the user who uploaded the file
     */
    public function uploader()
    {
        return $this->hasOne(User::class, 'id', 'uploaded_by');
    }

    public function generateRandomName()
    {
        $this->filename = Str::random(10);
    }

    public function getHostAttribute(): string
    {
        return 'https://i.'.Core::getDomain($this->secondary_domain);
    }

    public function getTotalSizeAttribute(): string
    {
        return $this->size + $this->additional_size;
    }

    public function getFilenames(): array {
        return [
            'full' => "{$this->filename}.{$this->extension}",
            'jpeg' => "{$this->filename}.jpg",
            'preview' => "{$this->filename}p.png",
        ];
    }

    public function getFilePaths(string $uploaddir, array $filenames): array {
        return [
            'full' => "$uploaddir/{$filenames['full']}",
            'jpeg' => "$uploaddir/{$filenames['jpeg']}",
            'preview' => "$uploaddir/{$filenames['preview']}",
        ];
    }

    public function getPreviewDimensions(): int {
        return min($this->width, $this->height, 300);
    }

    public function calculateFileSizes(?array $file_paths = null) {
        if ($file_paths === null) {
            $file_paths = $this->getFilePaths(UploadUtil::getUploadDirectory(), $this->getFilenames());
        }

        $full_file_size = filesize($file_paths['full']);
        $additional_file_sizes = [
            'jpeg' => filesize($file_paths['jpeg']),
            'preview' => filesize($file_paths['preview']),
        ];
        $this->size = $full_file_size === false ? 0 : $full_file_size;
        $this->additional_size = array_reduce(
            $additional_file_sizes,
            fn($carry, $item) => $carry + ($item === false ? 0 : $item),
            0
        );
    }
}
