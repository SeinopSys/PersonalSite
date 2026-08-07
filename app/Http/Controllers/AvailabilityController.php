<?php

namespace App\Http\Controllers;

use App\Data\AvailabilityResult;
use App\Data\TimeSlot;
use App\Models\CalendarHighlightToken;
use App\Models\User;
use App\Services\AvailabilityService;
use Dedoc\Scramble\Attributes\ApiResponse;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    #[Response(type: 'array{timezone: string, range: array{start: string, end: string}, free: list<array{start: string, end: string}>, highlighted: list<array{start: string, end: string, tentative?: bool}>, unavailable: list<array{start: string, end: string, tentative?: bool}>, sleep: list<array{start: string, end: string}>}')]
    #[QueryParameter('start', 'Start of the date range in YYYY-MM-DD format. Defaults to the current week\'s Monday.', required: false, type: 'string')]
    #[QueryParameter('end', 'End of the date range in YYYY-MM-DD format. Defaults to start date plus six days.', required: false, type: 'string')]
    #[QueryParameter('token', 'Base64url-encoded highlight token. Matching events are returned under a `highlighted` key.', required: true, type: 'string')]
    #[ApiResponse(status: 401, description: 'Missing or invalid highlight token.')]
    public function show(Request $request, string $name): JsonResponse
    {
        $user = User::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->whereNotNull('calendar_url')
            ->first();
        if (!$user) {
            return response()->json(['error' => 'User not found or no calendar configured'], 404);
        }

        $tokenStr = $request->input('token');
        $highlightToken = $tokenStr ? CalendarHighlightToken::findByBase64Url($tokenStr, $user->id) : null;
        if (!$highlightToken) {
            abort(401);
        }
        $highlightWords = $highlightToken->words->pluck('word')->toArray();

        $service = new AvailabilityService();
        $tz = $user->timezone ?? 'UTC';
        [$rangeStart, $rangeEnd] = $this->parseRange($request->input('start'), $request->input('end'), $tz);

        try {
            $icsContent = $service->fetchIcs($user->calendar_url);
        } catch (\Exception) {
            return response()->json(['error' => 'Failed to fetch calendar data'], 503);
        }

        $busyEvents = $service->parseIcsEvents($icsContent, $rangeStart, $rangeEnd, $tz);
        $settings = $user->availability_settings ?? [];

        // DND filtering is applied fresh per request (never cached), since whether a DND-named
        // event counts as busy depends on the token's bypass_dnd flag, which varies per request.
        $dndName = $user->dndEventName();
        $freeSlotEvents = $highlightToken->bypass_dnd
            ? array_values(array_filter($busyEvents, fn($e) => $e['name'] !== $dndName))
            : $busyEvents;

        $freeSlots = $service->computeRangeFreeSlots(
            $freeSlotEvents,
            $settings,
            $rangeStart->copy()->subDay(),
            $rangeEnd->copy()->addDay(),
            $tz
        );

        $sleepBlocks = $service->computeRangeSleepBlocks(
            $settings,
            $rangeStart->copy()->subDay(),
            $rangeEnd->copy()->addDay(),
            $tz
        );

        // Events named after the configured nap event count as sleep too, merged in with the
        // wake/sleep-window blocks above.
        $napEventName = $user->napEventName();
        $napBlocks = array_values(array_map(
            fn($e) => ['start' => $e['start'], 'end' => $e['end']],
            array_filter($freeSlotEvents, fn($e) => $e['name'] === $napEventName)
        ));
        if (!empty($napBlocks)) {
            $sleepBlocks = $service->mergeIntervals(array_merge($sleepBlocks, $napBlocks));
        }

        $cutoff = Carbon::now($tz)->subDay()->startOfDay();
        $freeSlots = array_filter($freeSlots, fn($s) => $s['end']->gt($cutoff));
        $sleepSlots = array_filter($sleepBlocks, fn($s) => $s['end']->gt($cutoff));

        $toSlot = fn($s) => new TimeSlot($s['start']->toAtomString(), $s['end']->toAtomString());
        $toEventSlot = fn($e) => new TimeSlot(
            $e['start']->toAtomString(),
            $e['end']->toAtomString(),
            $e['tentative'] ? true : null,
        );

        $matchedEvents = array_filter(
            $this->filterHighlightedEvents($busyEvents, $highlightWords),
            fn($e) => $e['end']->gt($cutoff)
        );
        $highlighted = array_values(array_map($toEventSlot, $matchedEvents));

        // Sleep takes precedence over busy time: an event's portion that falls within a sleep
        // block is not reported as unavailable, since it's already covered by the 'sleep' key.
        $unavailableEvents = array_filter($freeSlotEvents, fn($e) => $e['end']->gt($cutoff));
        $unavailableSegments = array_filter(
            $service->subtractSleepFromEvents($unavailableEvents, $sleepBlocks),
            fn($s) => $s['end']->gt($cutoff)
        );
        $unavailable = array_values(array_map($toEventSlot, $unavailableSegments));

        return response()->json(new AvailabilityResult(
            timezone: $tz,
            range: new TimeSlot($rangeStart->toAtomString(), $rangeEnd->toAtomString()),
            free: array_values(array_map($toSlot, $freeSlots)),
            highlighted: $highlighted,
            unavailable: $unavailable,
            sleep: array_values(array_map($toSlot, $sleepSlots)),
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

    private function filterHighlightedEvents(array $events, array $words): array
    {
        return (new AvailabilityService())->matchEventsByWords($events, $words);
    }
}
