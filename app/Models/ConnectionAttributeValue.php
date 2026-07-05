<?php

namespace App\Models;

use App\Util\JSON;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionAttributeValue extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['connection_id', 'attribute_definition_id', 'user_id', 'value'];

    protected $casts = ['value' => 'encrypted'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ConnectionAttributeDefinition::class, 'attribute_definition_id');
    }

    /** Decode the decrypted, JSON-encoded raw value into its typed PHP representation. */
    public function getTypedValueAttribute(): mixed
    {
        return $this->value === null ? null : JSON::Decode($this->value);
    }
}
