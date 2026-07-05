<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionEdge extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'connection_a_id', 'connection_b_id'];

    public function connectionA(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_a_id');
    }

    public function connectionB(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_b_id');
    }
}
