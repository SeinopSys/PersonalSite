<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ConnectionSource extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'name', 'category', 'icon_path'];

    protected $appends = ['icon_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class, 'source_id');
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null;
    }
}
