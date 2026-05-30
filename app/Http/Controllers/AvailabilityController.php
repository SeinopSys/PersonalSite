<?php

namespace App\Http\Controllers;

use App\Data\AvailabilityResult;
use App\Data\TimeSlot;
use App\Models\CalendarHighlightToken;
use App\Models\User;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Sabre\VObject\Reader;

class AvailabilityController extends Controller
{
    #[Response(type: 'array{timezone: string, range: array{start: string, end: string}, free: list<array{start: string, end: string}>, highlighted?: list<array{start: string, end: string}>}')]
    #[QueryParameter('start', 'Start of the date range in YYYY-MM-DD format. Defaults to the current week\'s Monday.', required: false, type: 'string')]
    #[QueryParameter('end', 'End of the date range in YYYY-MM-DD format. Defaults to start date plus six days.', required: false, type: 'string')]
    #[QueryParameter('token', 'Base64url-encoded highlight token. When supplied and valid, matching events are returned under a `highlighted` key.', required: false, type: 'string')]
    public function show(Request $request, string $name): JsonResponse
    {
        $user = User::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->whereNotNull('calendar_url')
            ->first();
        if (!$user) {
            return response()->json(['error' => 'User not found or no calendar configured'], 404);
        }

        $tz = $user->timezone ?? 'UTC';
        [$rangeStart, $rangeEnd] = $this->parseRange($request->input('start'), $request->input('end'), $tz);

        try {
            $icsContent = $this->fetchIcs($user->calendar_url);
        } catch (\Exception) {
            return response()->json(['error' => 'Failed to fetch calendar data'], 503);
        }

        $busyEvents = $this->parseIcsEvents($icsContent, $rangeStart, $rangeEnd, $tz);
        $settings = $user->availability_settings ?? [];
        $freeSlots = $this->computeRangeFreeSlots(
            $busyEvents,
            $settings,
            $rangeStart->copy()->subDay(),
            $rangeEnd->copy()->addDay(),
            $tz
        );

        $cutoff = Carbon::now($tz)->subDay()->startOfDay();
        $freeSlots = array_filter($freeSlots, fn($s) => $s['end']->gt($cutoff));

        $tokenStr = $request->input('token');
        $highlightWords = [];
        $tokenValid = false;
        if ($tokenStr) {
            $highlightToken = CalendarHighlightToken::findByBase64Url($tokenStr, $user->id);
            if ($highlightToken) {
                $tokenValid = true;
                $highlightWords = $highlightToken->words->pluck('word')->toArray();
            }
        }

        $toSlot = fn($s) => new TimeSlot($s['start']->toAtomString(), $s['end']->toAtomString());

        $highlighted = null;
        if ($tokenValid) {
            $highlighted = array_values(array_map($toSlot, $this->filterHighlightedEvents($busyEvents, $highlightWords)));
        }

        return response()->json(new AvailabilityResult(
            timezone: $tz,
            range: new TimeSlot($rangeStart->toAtomString(), $rangeEnd->toAtomString()),
            free: array_values(array_map($toSlot, $freeSlots)),
            highlighted: $highlighted,
        ));
    }

    private function parseRange(?string $start, ?string $end, string $tz): array
    {
        if ($start) {
            $rangeStart = Carbon::parse($start, $tz)->startOfDay();
            $rangeEnd = $end
                ? Carbon::parse($end, $tz)->endOfDay()
                : $rangeStart->copy()->addDays(6)->endOfDay();
        } else {
            $rangeStart = Carbon::now($tz)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $rangeEnd = Carbon::now($tz)->endOfWeek(Carbon::SUNDAY)->endOfDay();
        }

        return [$rangeStart, $rangeEnd];
    }

    private function fetchIcs(string $url): string
    {
        $cacheKey = 'ics_' . md5($url);
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($url) {
            $client = new Client(['timeout' => 10]);
            return (string)$client->get($url)->getBody();
        });
    }

    private function parseIcsEvents(string $icsContent, Carbon $rangeStart, Carbon $rangeEnd, string $tz): array
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

    private function parseEventDateTime($dateProperty, string $tz): Carbon
    {
        $valueType = strtoupper((string)($dateProperty['VALUE'] ?? ''));

        if ($valueType === 'DATE') {
            return Carbon::parse((string)$dateProperty->getValue(), $tz)->startOfDay();
        }

        return Carbon::instance($dateProperty->getDateTime())->setTimezone($tz);
    }

    private function filterHighlightedEvents(array $events, array $words): array
    {
        if (empty($words)) {
            return [];
        }

        $matched = [];
        foreach ($events as $event) {
            foreach ($words as $word) {
                if (strpos($event['name'], $word) !== false) {
                    $matched[] = $event;
                    break;
                }
            }
        }

        return $matched;
    }

    private function computeRangeFreeSlots(array $busyEvents, array $settings, Carbon $rangeStart, Carbon $rangeEnd, string $tz): array
    {
        $dowNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $freeSlots = [];
        $day = $rangeStart->copy()->setTimezone($tz)->startOfDay();

        while ($day->lte($rangeEnd)) {
            $dayName = $dowNames[$day->dayOfWeek];
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
}
