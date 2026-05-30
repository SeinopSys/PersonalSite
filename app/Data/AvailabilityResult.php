<?php

namespace App\Data;

class AvailabilityResult
{
    /**
     * @param string          $timezone    The user's configured IANA timezone identifier (e.g. "Europe/Budapest").
     * @param TimeSlot        $range       The queried date range; start is the beginning of the first day, end is the end of the last day.
     * @param TimeSlot[]      $free        Free time slots within the queried range, sorted chronologically. Each slot is a window when the user is available.
     * @param TimeSlot[]|null $highlighted Time slots of calendar events whose names matched the token's words. Only present when a valid token is supplied. These events still count as busy.
     */
    public function __construct(
        public string $timezone,
        public TimeSlot $range,
        public array $free,
        public ?array $highlighted,
    ) {}
}
