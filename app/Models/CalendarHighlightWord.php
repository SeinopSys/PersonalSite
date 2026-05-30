<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarHighlightWord extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['token_id', 'user_id', 'word'];

    public function highlightToken(): BelongsTo
    {
        return $this->belongsTo(CalendarHighlightToken::class, 'token_id');
    }
}
