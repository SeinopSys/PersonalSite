<?php

namespace App\Data;

class AvailabilityResult
{
    /**
     * @param string     $timezone    The user's configured IANA timezone identifier (e.g. "Europe/Budapest").
     * @param TimeSlot   $range       The queried date range; start is the beginning of the first day, end is the end of the last day.
     * @param TimeSlot[] $free        Free time slots within the queried range, sorted chronologically. Each slot is a window when the user is available.
     * @param TimeSlot[] $highlighted Time slots of calendar events whose names matched the token's words. These events still count as busy. Tentative events (name ending in "(?)") include a `tentative: true` field.
     * @param TimeSlot[] $unavailable Time slots of all calendar events currently making the user unavailable (i.e. those shaping `free`), sorted chronologically. Tentative events (name ending in "(?)") include a `tentative: true` field.
     */
    public function __construct(
        public string $timezone,
        public TimeSlot $range,
        public array $free,
        public array $highlighted,
        public array $unavailable,
    ) {}
}
