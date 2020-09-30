<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\ImageUpload
 *
 * @property string $user_id
 * @property string $upload_key
 * @property-read User $user
 * @method static Builder|ImageUpload whereUploadKey($value)
 * @method static Builder|ImageUpload whereUserId($value)
 * @method static Builder|ImageUpload newModelQuery()
 * @method static Builder|ImageUpload newQuery()
 * @method static Builder|ImageUpload query()
 */
class ImageUpload extends Model
{
    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'upload_key',
    ];

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function generateUploadKey()
    {
        $this->upload_key = rtrim(base64_encode(random_bytes(40)), '=');
    }
}
