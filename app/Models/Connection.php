<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connection extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'name', 'met_at', 'source_id',
        'introduced_by_connection_id', 'highlight_token_id', 'archived',
    ];

    protected $casts = [
        'name' => 'encrypted',
        'met_at' => 'encrypted',
        'archived' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ConnectionSource::class, 'source_id');
    }

    public function introducedBy(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'introduced_by_connection_id');
    }

    public function introduced(): HasMany
    {
        return $this->hasMany(Connection::class, 'introduced_by_connection_id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ConnectionAttributeValue::class);
    }

    public function highlightToken(): BelongsTo
    {
        return $this->belongsTo(CalendarHighlightToken::class, 'highlight_token_id');
    }

    public function edgesAsA(): HasMany
    {
        return $this->hasMany(ConnectionEdge::class, 'connection_a_id');
    }

    public function edgesAsB(): HasMany
    {
        return $this->hasMany(ConnectionEdge::class, 'connection_b_id');
    }

    /** met_at is stored as an encrypted ISO-8601 date string, since the encrypted and date casts can't stack. */
    public function getMetAtDateAttribute(): ?Carbon
    {
        return $this->met_at ? Carbon::parse($this->met_at) : null;
    }
}
