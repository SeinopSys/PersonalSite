<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionSourceCategory extends Model
{
    use Uuids;

    /** Swatch shown/assigned for a category before the user has picked a color of their own. */
    public const DEFAULT_COLOR = '#993366';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'name', 'color'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
