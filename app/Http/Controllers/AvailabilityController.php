<?php

namespace App\Http\Controllers;

use App\Data\AvailabilityResult;
use App\Data\TimeSlot;
use App\Models\CalendarHighlightToken;
use App\Models\User;
use App\Services\AvailabilityService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $freeSlots = $service->computeRangeFreeSlots(
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
            $matchedEvents = array_filter(
                $this->filterHighlightedEvents($busyEvents, $highlightWords),
                fn($e) => $e['end']->gt($cutoff)
            );
            $highlighted = array_values(array_map($toSlot, $matchedEvents));
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

    private function filterHighlightedEvents(array $events, array $words): array
    {
        return (new AvailabilityService())->matchEventsByWords($events, $words);
    }
}
