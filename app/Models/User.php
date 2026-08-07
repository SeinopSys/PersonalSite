<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;

/**
 * App\Models\User
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
 * @property-read ImageUploadKey|null $rootImageUploadKey
 * @property-read Collection|ImageUploadKey[] $imageUploadKeys
 * @property-read Collection|UploadFolder[] $uploadFolders
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
 * @property-read int|null $notifications_count
 * @property-read int|null $uploads_count
 * @method static Builder|User newModelQuery()
 * @method static Builder|User newQuery()
 * @method static Builder|User query()
 * @property string|null $email_verified_at
 * @method static Builder|User whereEmailVerifiedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use Notifiable, Uuids;

    /** Default calendar event name treated as "do not disturb" until the user customizes it. */
    public const DEFAULT_DND_EVENT_NAME = 'Do not disturb';

    /** Default calendar event name treated as sleep time until the user customizes it. */
    public const DEFAULT_NAP_EVENT_NAME = 'Taking a nap';

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
        'name', 'email', 'password', 'lang', 'role', 'calendar_url', 'availability_settings', 'timezone',
        'dnd_event_name', 'nap_event_name',
    ];

    protected $casts = [
        'availability_settings' => 'array',
        'calendar_url' => 'encrypted',
        'two_factor_secret' => 'encrypted',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret',
    ];

    /** The configured "do not disturb" event name, falling back to the default when unset. */
    public function dndEventName(): string
    {
        return $this->dnd_event_name ?? self::DEFAULT_DND_EVENT_NAME;
    }

    /** The configured "nap" event name treated as sleep time, falling back to the default when unset. */
    public function napEventName(): string
    {
        return $this->nap_event_name ?? self::DEFAULT_NAP_EVENT_NAME;
    }

    public function getGravatar($size = 50)
    {
        return sprintf("https://s.gravatar.com/avatar/%s?s=$size", md5($this->email));
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
     * Get all of the user's upload keys (root key has folder_id === null, plus one per folder)
     */
    public function imageUploadKeys()
    {
        return $this->hasMany(ImageUploadKey::class);
    }

    /**
     * Get the user's root-level upload key, if uploading is enabled
     */
    public function rootImageUploadKey()
    {
        return $this->hasOne(ImageUploadKey::class)->whereNull('folder_id');
    }

    public function uploadFolders()
    {
        return $this->hasMany(UploadFolder::class);
    }

    public function uploads()
    {
        return $this->hasMany(Upload::class, 'uploaded_by', 'id');
    }

    public function highlightTokens(): HasMany
    {
        return $this->hasMany(CalendarHighlightToken::class);
    }

    public function sleepExceptions(): HasMany
    {
        return $this->hasMany(SleepException::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    public function connectionSources(): HasMany
    {
        return $this->hasMany(ConnectionSource::class);
    }

    public function connectionAttributeDefinitions(): HasMany
    {
        return $this->hasMany(ConnectionAttributeDefinition::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_confirmed_at);
    }
}
