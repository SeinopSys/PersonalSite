<?php

namespace App\Data;

class TimeSlot implements \JsonSerializable
{
    /**
     * @param string    $start     ISO 8601 datetime.
     * @param string    $end       ISO 8601 datetime.
     * @param bool|null $tentative Whether the event is tentative. Omitted entirely for non-tentative slots.
     */
    public function __construct(
        public string $start,
        public string $end,
        public ?bool $tentative = null,
    ) {}

    public function jsonSerialize(): array
    {
        $data = ['start' => $this->start, 'end' => $this->end];

        if ($this->tentative !== null) {
            $data['tentative'] = $this->tentative;
        }

        return $data;
    }
}
