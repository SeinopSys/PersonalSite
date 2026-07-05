<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Sabre\VObject\Reader;

class AvailabilityService
{
    public const DAY_NAMES = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    public function fetchIcs(string $url): string
    {
        $cacheKey = 'ics_' . md5($url);
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($url) {
            $client = new Client(['timeout' => 10]);
            return (string)$client->get($url)->getBody();
        });
    }

    public function parseIcsEvents(string $icsContent, Carbon $rangeStart, Carbon $rangeEnd, string $tz): array
    {
        $calendar = Reader::read($icsContent);
        $expandStart = $rangeStart->copy()->utc()->subDay();
        $expandEnd = $rangeEnd->copy()->utc()->addDay();
        $calendar = $calendar->expand(
            \DateTimeImmutable::createFromInterface($expandStart),
            \DateTimeImmutable::createFromInterface($expandEnd)
        );

        $events = [];
        if (!isset($calendar->VEVENT)) {
            return $events;
        }

        foreach ($calendar->VEVENT as $event) {
            $start = $this->parseEventDateTime($event->DTSTART, $tz);
            $end = isset($event->DTEND)
                ? $this->parseEventDateTime($event->DTEND, $tz)
                : $start->copy()->addHour();

            $events[] = [
                'start' => $start,
                'end'   => $end,
                'name'  => isset($event->SUMMARY) ? (string)$event->SUMMARY : '',
            ];
        }

        usort($events, fn($a, $b) => $a['start']->timestamp <=> $b['start']->timestamp);

        return $events;
    }

    public function parseEventDateTime($dateProperty, string $tz): Carbon
    {
        $valueType = strtoupper((string)($dateProperty['VALUE'] ?? ''));

        if ($valueType === 'DATE') {
            return Carbon::parse((string)$dateProperty->getValue(), $tz)->startOfDay();
        }

        return Carbon::instance($dateProperty->getDateTime())->setTimezone($tz);
    }

    public function computeRangeFreeSlots(array $busyEvents, array $settings, Carbon $rangeStart, Carbon $rangeEnd, string $tz): array
    {
        $freeSlots = [];
        $day = $rangeStart->copy()->setTimezone($tz)->startOfDay();

        while ($day->lte($rangeEnd)) {
            $dayName = self::DAY_NAMES[$day->dayOfWeek];
            $daySetting = $settings[$dayName] ?? null;

            if ($daySetting !== null && !($daySetting['available'] ?? true)) {
                $day->addDay();
                continue;
            }

            if (!empty($daySetting['wake'])) {
                [$wh, $wm] = array_map('intval', explode(':', $daySetting['wake']));
                $windowStart = $day->copy()->setTime($wh, $wm);
            } else {
                $windowStart = $day->copy()->startOfDay();
            }

            if (!empty($daySetting['sleep'])) {
                [$sh, $sm] = array_map('intval', explode(':', $daySetting['sleep']));
                $windowEnd = $day->copy()->setTime($sh, $sm);
                if ($windowEnd->lte($windowStart)) {
                    $windowEnd->addDay();
                }
            } else {
                $windowEnd = $day->copy()->endOfDay();
            }

            if ($windowStart->lt($rangeStart)) $windowStart = $rangeStart->copy();
            if ($windowEnd->gt($rangeEnd)) $windowEnd = $rangeEnd->copy();

            if ($windowStart->gte($windowEnd)) {
                $day->addDay();
                continue;
            }

            $dayFree = $this->subtractBusy($busyEvents, $windowStart, $windowEnd);
            $freeSlots = array_merge($freeSlots, $dayFree);
            $day->addDay();
        }

        return $freeSlots;
    }

    private function subtractBusy(array $busyEvents, Carbon $windowStart, Carbon $windowEnd): array
    {
        $slots = [['start' => $windowStart->copy(), 'end' => $windowEnd->copy()]];

        foreach ($busyEvents as $event) {
            $es = $event['start'];
            $ee = $event['end'];
            $newSlots = [];

            foreach ($slots as $slot) {
                if ($ee->lte($slot['start']) || $es->gte($slot['end'])) {
                    $newSlots[] = $slot;
                } elseif ($es->lte($slot['start']) && $ee->gte($slot['end'])) {
                    // event fully covers slot — removed
                } elseif ($es->lte($slot['start'])) {
                    $newSlots[] = ['start' => $ee->copy(), 'end' => $slot['end']];
                } elseif ($ee->gte($slot['end'])) {
                    $newSlots[] = ['start' => $slot['start'], 'end' => $es->copy()];
                } else {
                    $newSlots[] = ['start' => $slot['start'], 'end' => $es->copy()];
                    $newSlots[] = ['start' => $ee->copy(), 'end' => $slot['end']];
                }
            }

            $slots = $newSlots;
        }

        return $slots;
    }

    /**
     * Returns the total available window in minutes for a given day name, based on availability settings.
     * Returns 0 for days marked as unavailable.
     */
    public function dayWindowMinutes(string $dayName, array $settings): int
    {
        $s = $settings[$dayName] ?? null;

        if ($s !== null && !($s['available'] ?? true)) {
            return 0;
        }

        $wake = 0;
        $sleep = 1440;

        if ($s !== null && !empty($s['wake'])) {
            [$wh, $wm] = array_map('intval', explode(':', $s['wake']));
            $wake = $wh * 60 + $wm;
        }
        if ($s !== null && !empty($s['sleep'])) {
            [$sh, $sm] = array_map('intval', explode(':', $s['sleep']));
            $sleep = $sh * 60 + $sm;
            if ($sleep <= $wake) {
                $sleep += 1440;
            }
        }

        return $sleep - $wake;
    }

    /**
     * For each day in [rangeStart, rangeEnd], intersects events that pass $filter with the
     * configured daily window, merges overlapping intervals, and returns per-date minute counts.
     * Keyed by 'Y-m-d'; days marked unavailable are omitted.
     */
    public function computeFilteredMinutesByDate(
        array $events,
        array $settings,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        string $tz,
        callable $filter
    ): array {
        $filtered = array_values(array_filter($events, $filter));
        $byDate = [];
        $day = $rangeStart->copy()->setTimezone($tz)->startOfDay();

        while ($day->lte($rangeEnd)) {
            $dayName  = self::DAY_NAMES[$day->dayOfWeek];
            $daySetting = $settings[$dayName] ?? null;

            if ($daySetting !== null && !($daySetting['available'] ?? true)) {
                $day->addDay();
                continue;
            }

            if (!empty($daySetting['wake'])) {
                [$wh, $wm] = array_map('intval', explode(':', $daySetting['wake']));
                $windowStart = $day->copy()->setTime($wh, $wm);
            } else {
                $windowStart = $day->copy()->startOfDay();
            }

            if (!empty($daySetting['sleep'])) {
                [$sh, $sm] = array_map('intval', explode(':', $daySetting['sleep']));
                $windowEnd = $day->copy()->setTime($sh, $sm);
                if ($windowEnd->lte($windowStart)) {
                    $windowEnd->addDay();
                }
            } else {
                $windowEnd = $day->copy()->endOfDay();
            }

            if ($windowStart->lt($rangeStart)) $windowStart = $rangeStart->copy();
            if ($windowEnd->gt($rangeEnd))     $windowEnd   = $rangeEnd->copy();

            if ($windowStart->gte($windowEnd)) {
                $day->addDay();
                continue;
            }

            $intervals = [];
            foreach ($filtered as $event) {
                $es = $event['start']->lt($windowStart) ? $windowStart->copy() : $event['start']->copy();
                $ee = $event['end']->gt($windowEnd)     ? $windowEnd->copy()   : $event['end']->copy();
                if ($es->lt($ee)) {
                    $intervals[] = [$es->timestamp, $ee->timestamp];
                }
            }

            $minutes = 0;
            if (!empty($intervals)) {
                usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);
                $merged = [$intervals[0]];
                for ($i = 1, $c = count($intervals); $i < $c; $i++) {
                    $last = &$merged[count($merged) - 1];
                    if ($intervals[$i][0] <= $last[1]) {
                        $last[1] = max($last[1], $intervals[$i][1]);
                    } else {
                        $merged[] = $intervals[$i];
                    }
                }
                foreach ($merged as [$s, $e]) {
                    $minutes += (int)round(($e - $s) / 60);
                }
            }

            $byDate[$day->format('Y-m-d')] = $minutes;
            $day->addDay();
        }

        return $byDate;
    }

    public function sumSlotMinutes(array $slots): int
    {
        $total = 0;
        foreach ($slots as $slot) {
            $total += (int)$slot['start']->diffInMinutes($slot['end']);
        }
        return $total;
    }

    /** Events whose name contains at least one of the given words (substring match). */
    public function matchEventsByWords(array $events, array $words): array
    {
        if (empty($words)) {
            return [];
        }

        $matched = [];
        foreach ($events as $event) {
            foreach ($words as $word) {
                if (str_contains($event['name'], $word)) {
                    $matched[] = $event;
                    break;
                }
            }
        }

        return $matched;
    }
}
