<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;

/**
 * App\User
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $lang
 * @property string $role
 * @property-read ImageUpload $imageUpload
 * @property-read DatabaseNotificationCollection|DatabaseNotification[] $notifications
 * @property-read Collection|Upload[] $uploads
 * @method static Builder|User whereCreatedAt($value)
 * @method static Builder|User whereEmail($value)
 * @method static Builder|User whereId($value)
 * @method static Builder|User whereLang($value)
 * @method static Builder|User whereName($value)
 * @method static Builder|User wherePassword($value)
 * @method static Builder|User whereRememberToken($value)
 * @method static Builder|User whereRole($value)
 * @method static Builder|User whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property-read int|null $notifications_count
 * @property-read int|null $uploads_count
 * @method static Builder|User newModelQuery()
 * @method static Builder|User newQuery()
 * @method static Builder|User query()
 */
class User extends Authenticatable
{
    use Notifiable;
    use Uuids;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'lang', 'role',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function getGravatar($size = 50)
    {
        return 'https://s.gravatar.com/avatar/'.md5($this->email)."?s=$size";
    }

    public function getRelativeUploadDirectory($startingSlash = true)
    {
        return ($startingSlash ? '/' : '').'img/uploads';
    }

    public function getUploadDirectory()
    {
        return public_path($this->getRelativeUploadDirectory(false));
    }

    /**
     * Get the upload status data of the user
     */
    public function imageUpload()
    {
        return $this->hasOne('App\ImageUpload');
    }

    public function uploads()
    {
        return $this->hasMany('App\Upload', 'uploaded_by', 'id');
    }
}
