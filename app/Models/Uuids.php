<?php

namespace App\Models;

use Webpatser\Uuid\Uuid;

trait Uuids
{

    /**
     * Boot function from laravel.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // An id set beforehand is kept, which is what lets an export be imported with its ids (and so its links) intact
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Uuid::generate(4)->string;
            }
        });
    }
}
