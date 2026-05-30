<?php

namespace App\Data;

class TimeSlot
{
    /**
     * @param string $start ISO 8601 datetime.
     * @param string $end   ISO 8601 datetime.
     */
    public function __construct(
        public string $start,
        public string $end,
    ) {}
}
