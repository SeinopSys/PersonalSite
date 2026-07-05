<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectionAttributeDefinition extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'label', 'type', 'options', 'sort_order'];

    protected $casts = ['options' => 'array'];

    public const TYPES = ['number', 'numeric_range', 'enum', 'text', 'textarea', 'radio', 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ConnectionAttributeValue::class, 'attribute_definition_id');
    }
}
