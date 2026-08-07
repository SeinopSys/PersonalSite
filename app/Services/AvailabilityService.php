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

            $name = isset($event->SUMMARY) ? (string)$event->SUMMARY : '';

            $events[] = [
                'start'     => $start,
                'end'       => $end,
                'name'      => $name,
                'tentative' => str_ends_with(rtrim($name), '(?)'),
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

    /**
     * Returns sleep blocks (time outside the configured wake/sleep window, and all of any day
     * marked unavailable) within [rangeStart, rangeEnd]. Directly adjacent or overlapping blocks
     * — e.g. a day marked unavailable next to another day's pre-wake gap — are merged into one.
     * Dates covered by $exceptions (list of ['start' => 'Y-m-d', 'end' => 'Y-m-d'], inclusive)
     * are treated as fully awake for the whole day instead, regardless of the weekly settings —
     * no automatic sleep block is generated for them. Calendar events named after the user's nap
     * event are merged in separately by the caller and are unaffected by this.
     */
    public function computeRangeSleepBlocks(array $settings, Carbon $rangeStart, Carbon $rangeEnd, string $tz, array $exceptions = []): array
    {
        $awakeWindows = [];
        $day = $rangeStart->copy()->setTimezone($tz)->startOfDay();

        while ($day->lte($rangeEnd)) {
            if ($this->dateInExceptions($day->format('Y-m-d'), $exceptions)) {
                $windowStart = $day->copy()->startOfDay();
                $windowEnd = $day->copy()->addDay()->startOfDay();

                if ($windowStart->lt($rangeStart)) $windowStart = $rangeStart->copy();
                if ($windowEnd->gt($rangeEnd)) $windowEnd = $rangeEnd->copy();

                if ($windowStart->lt($windowEnd)) {
                    $awakeWindows[] = ['start' => $windowStart, 'end' => $windowEnd];
                }

                $day->addDay();
                continue;
            }

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

            if ($windowStart->lt($windowEnd)) {
                $awakeWindows[] = ['start' => $windowStart, 'end' => $windowEnd];
            }

            $day->addDay();
        }

        $mergedAwake = $this->mergeIntervals($awakeWindows);

        $sleepBlocks = [];
        $cursor = $rangeStart->copy();
        foreach ($mergedAwake as $window) {
            if ($window['start']->gt($cursor)) {
                $sleepBlocks[] = ['start' => $cursor->copy(), 'end' => $window['start']->copy()];
            }
            if ($window['end']->gt($cursor)) {
                $cursor = $window['end']->copy();
            }
        }
        if ($cursor->lt($rangeEnd)) {
            $sleepBlocks[] = ['start' => $cursor->copy(), 'end' => $rangeEnd->copy()];
        }

        return $sleepBlocks;
    }

    /** Whether $dateStr ('Y-m-d') falls within any of the given inclusive ['start', 'end'] date ranges. */
    private function dateInExceptions(string $dateStr, array $exceptions): bool
    {
        foreach ($exceptions as $exception) {
            $start = $exception['start'] ?? null;
            $end = $exception['end'] ?? null;
            if ($start && $end && $dateStr >= $start && $dateStr <= $end) {
                return true;
            }
        }

        return false;
    }

    /** Sorts intervals by start and merges any that overlap or directly touch into one. */
    public function mergeIntervals(array $intervals): array
    {
        usort($intervals, fn($a, $b) => $a['start']->timestamp <=> $b['start']->timestamp);

        $merged = [];
        foreach ($intervals as $interval) {
            if (!empty($merged) && $interval['start']->lte($merged[count($merged) - 1]['end'])) {
                $last = &$merged[count($merged) - 1];
                if ($interval['end']->gt($last['end'])) {
                    $last['end'] = $interval['end'];
                }
                unset($last);
            } else {
                $merged[] = $interval;
            }
        }

        return $merged;
    }

    private function subtractBusy(array $busyEvents, Carbon $windowStart, Carbon $windowEnd): array
    {
        return $this->subtractIntervals(
            [['start' => $windowStart->copy(), 'end' => $windowEnd->copy()]],
            $busyEvents
        );
    }

    /** Removes the portions of $slots that overlap any interval in $subtract. */
    private function subtractIntervals(array $slots, array $subtract): array
    {
        foreach ($subtract as $interval) {
            $es = $interval['start'];
            $ee = $interval['end'];
            $newSlots = [];

            foreach ($slots as $slot) {
                if ($ee->lte($slot['start']) || $es->gte($slot['end'])) {
                    $newSlots[] = $slot;
                } elseif ($es->lte($slot['start']) && $ee->gte($slot['end'])) {
                    // interval fully covers slot — removed
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
     * Clips each event against $sleepBlocks so sleep takes precedence over busy time: events
     * fully within a sleep block are dropped, and events straddling one are split around it.
     * Each resulting segment keeps its originating event's 'tentative' flag.
     */
    public function subtractSleepFromEvents(array $events, array $sleepBlocks): array
    {
        $segments = [];

        foreach ($events as $event) {
            $remaining = $this->subtractIntervals(
                [['start' => $event['start'], 'end' => $event['end']]],
                $sleepBlocks
            );

            foreach ($remaining as $slot) {
                $segments[] = [
                    'start'     => $slot['start'],
                    'end'       => $slot['end'],
                    'tentative' => $event['tentative'] ?? false,
                ];
            }
        }

        usort($segments, fn($a, $b) => $a['start']->timestamp <=> $b['start']->timestamp);

        return $segments;
    }

    /**
     * Merges directly adjacent or overlapping event segments into one, e.g. two back-to-back
     * calendar events reported as separate busy blocks. Tentative time takes precedence over
     * confirmed time for its own exact span — a tentative event is carved out of any confirmed
     * event it overlaps, rather than the two being reported as redundant overlapping ranges — so
     * a tentative segment never merges into a confirmed one or vice versa.
     */
    public function mergeEventSegments(array $segments): array
    {
        $tentative = array_values(array_filter($segments, fn($s) => $s['tentative'] ?? false));
        $confirmed = array_values(array_filter($segments, fn($s) => !($s['tentative'] ?? false)));

        $mergedTentative = $this->mergeIntervals($tentative);

        $carvedConfirmed = [];
        foreach ($confirmed as $event) {
            $remaining = $this->subtractIntervals(
                [['start' => $event['start'], 'end' => $event['end']]],
                $mergedTentative
            );
            foreach ($remaining as $slot) {
                $carvedConfirmed[] = $slot + ['tentative' => false];
            }
        }
        $mergedConfirmed = $this->mergeIntervals($carvedConfirmed);

        $merged = array_merge($mergedTentative, $mergedConfirmed);
        usort($merged, fn($a, $b) => $a['start']->timestamp <=> $b['start']->timestamp);

        return $merged;
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

    /**
     * Events whose name contains a "with <token>" or "w/ <token>" clause where at least one
     * comma-separated token (substring) matches one of the given words. Events without such a
     * clause are never matched, regardless of the words list. Matched events are returned with
     * an added 'activity' key: the freetext preceding "with"/"w/", trimmed of trailing whitespace.
     */
    public function matchEventsByActivityClause(array $events, array $words): array
    {
        if (empty($words)) {
            return [];
        }

        $matched = [];
        foreach ($events as $event) {
            if (!preg_match('/^(.*?)\b(?:with|w\/)\s+(.+)$/i', $event['name'], $m)) {
                continue;
            }

            $tokens = array_filter(array_map('trim', explode(',', $m[2])), fn($t) => $t !== '');
            if (empty($tokens)) {
                continue;
            }

            foreach ($words as $word) {
                $isMatch = false;
                foreach ($tokens as $token) {
                    if (str_contains($token, $word)) {
                        $isMatch = true;
                        break;
                    }
                }
                if ($isMatch) {
                    $matched[] = $event + ['activity' => rtrim($m[1])];
                    break;
                }
            }
        }

        return $matched;
    }
}
