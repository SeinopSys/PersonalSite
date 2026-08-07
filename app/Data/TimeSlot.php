<?php

namespace App\Data;

class TimeSlot implements \JsonSerializable
{
    /**
     * @param string      $start     ISO 8601 datetime.
     * @param string      $end       ISO 8601 datetime.
     * @param bool|null   $tentative Whether the event is tentative. Omitted entirely for non-tentative slots.
     * @param string|null $activity  Freetext activity name parsed from a "with <token>"/"w/ <token>" clause in the event name. Omitted entirely when not applicable.
     */
    public function __construct(
        public string $start,
        public string $end,
        public ?bool $tentative = null,
        public ?string $activity = null,
    ) {}

    public function jsonSerialize(): array
    {
        $data = ['start' => $this->start, 'end' => $this->end];

        if ($this->tentative !== null) {
            $data['tentative'] = $this->tentative;
        }

        if ($this->activity !== null) {
            $data['activity'] = $this->activity;
        }

        return $data;
    }
}
