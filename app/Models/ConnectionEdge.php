<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single ConnMan-style relationship edge: `from` is always a Connection, `to` is either another
 * Connection or a ConnectionSource (exactly one of to_connection_id/to_source_id is set - enforced by a
 * DB check constraint). `type` is 'one_way' ("met/introduced through" - directional) or 'bi_directional'
 * ("know each other" - no particular direction). There's no limit on how many edges a connection can
 * have of either kind; unlike earlier iterations of this feature, there's no separate "primary" field.
 */
class ConnectionEdge extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'from_connection_id', 'to_connection_id', 'to_source_id', 'type'];

    public const TYPE_ONE_WAY = 'one_way';
    public const TYPE_BI_DIRECTIONAL = 'bi_directional';
    public const TYPES = [self::TYPE_ONE_WAY, self::TYPE_BI_DIRECTIONAL];

    public function fromConnection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'from_connection_id');
    }

    public function toConnection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'to_connection_id');
    }

    public function toSource(): BelongsTo
    {
        return $this->belongsTo(ConnectionSource::class, 'to_source_id');
    }
}
