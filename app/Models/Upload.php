<?php

namespace App\Models;

use App\Util\Core;
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
 * @property string|null $uploaded_at
 * @property int|null $width
 * @property int|null $height
 * @property bool $secondary_domain
 * @property-read User $uploader
 * @property-read string $host
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
}
