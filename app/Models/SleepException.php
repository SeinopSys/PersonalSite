<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SleepException extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'start_date', 'end_date', 'label'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
