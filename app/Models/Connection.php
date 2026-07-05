<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connection extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'name', 'highlight_token_id', 'archived'];

    protected $casts = [
        'name' => 'encrypted',
        'archived' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ConnectionAttributeValue::class);
    }

    public function highlightToken(): BelongsTo
    {
        return $this->belongsTo(CalendarHighlightToken::class, 'highlight_token_id');
    }

    /** Edges where this connection is the subject - "I met via this source" / "I was introduced by/know this person". */
    public function edgesFrom(): HasMany
    {
        return $this->hasMany(ConnectionEdge::class, 'from_connection_id');
    }

    /** Edges where this connection is the target of another connection's edge (reverse lookup). */
    public function edgesTo(): HasMany
    {
        return $this->hasMany(ConnectionEdge::class, 'to_connection_id');
    }
}
