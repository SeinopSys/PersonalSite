<?php

namespace Tests\Unit;

use App\Http\Controllers\AvailabilityController;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AvailabilityControllerTest extends TestCase
{
    private function invokePrivate(object $object, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod($object::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object, ...$args);
    }
    public function test_parse_ics_events_includes_event_that_starts_previous_day_and_overlaps_range_start(): void
    {
        $service = new AvailabilityService();
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
            'BEGIN:VEVENT',
            'UID:test-overnight',
            'DTSTART:20260420T230000Z',
            'DTEND:20260421T010000Z',
            'SUMMARY:Overnight Event',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $rangeStart = Carbon::parse('2026-04-21 00:00:00', 'UTC');
        $rangeEnd = Carbon::parse('2026-04-21 23:59:59', 'UTC');

        $events = $service->parseIcsEvents($ics, $rangeStart, $rangeEnd, 'UTC');

        $this->assertCount(1, $events);
        $this->assertSame('2026-04-20T23:00:00+00:00', $events[0]['start']->toAtomString());
        $this->assertSame('2026-04-21T01:00:00+00:00', $events[0]['end']->toAtomString());
    }

    public function test_parse_ics_events_includes_event_that_starts_day_after_range_end(): void
    {
        $service = new AvailabilityService();
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
            'BEGIN:VEVENT',
            'UID:test-next-day',
            'DTSTART:20260422T003000Z',
            'DTEND:20260422T013000Z',
            'SUMMARY:Next Day Event',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $rangeStart = Carbon::parse('2026-04-21 00:00:00', 'UTC');
        $rangeEnd = Carbon::parse('2026-04-21 23:59:59', 'UTC');

        $events = $service->parseIcsEvents($ics, $rangeStart, $rangeEnd, 'UTC');

        $this->assertCount(1, $events);
        $this->assertSame('2026-04-22T00:30:00+00:00', $events[0]['start']->toAtomString());
        $this->assertSame('2026-04-22T01:30:00+00:00', $events[0]['end']->toAtomString());
    }

    public function test_compute_range_free_slots_honors_extended_range_for_cross_midnight_sleep_window(): void
    {
        $service = new AvailabilityService();

        $busyEvents = [[
            'start' => Carbon::parse('2026-04-22 00:30:00', 'UTC'),
            'end' => Carbon::parse('2026-04-22 01:00:00', 'UTC'),
            'name' => '',
        ]];

        $settings = [
            'monday' => ['available' => true, 'wake' => '09:00', 'sleep' => '01:00'],
            'tuesday' => ['available' => true, 'wake' => '09:00', 'sleep' => '01:00'],
            'wednesday' => ['available' => true, 'wake' => '09:00', 'sleep' => '01:00'],
            'thursday' => ['available' => true, 'wake' => '09:00', 'sleep' => '01:00'],
            'friday' => ['available' => true, 'wake' => '09:00', 'sleep' => '01:00'],
            'saturday' => ['available' => true, 'wake' => '09:00', 'sleep' => '01:00'],
            'sunday' => ['available' => true, 'wake' => '09:00', 'sleep' => '01:00'],
        ];

        $rangeStart = Carbon::parse('2026-04-20 00:00:00', 'UTC');
        $rangeEnd = Carbon::parse('2026-04-21 23:59:59', 'UTC');

        $freeSlots = $service->computeRangeFreeSlots(
            $busyEvents,
            $settings,
            $rangeStart->copy()->subDay(),
            $rangeEnd->copy()->addDay(),
            'UTC'
        );

        // Tuesday sleep=01:00 extends the window past midnight into Wednesday.
        // The busy event at 00:30–01:00 Wed cuts the window short, so there must be a
        // free slot that ends at exactly 2026-04-22T00:30 (the cross-midnight portion is preserved).
        $slotFound = false;
        foreach ($freeSlots as $slot) {
            if ($slot['end']->toAtomString() === '2026-04-22T00:30:00+00:00'
                && $slot['start']->lte(Carbon::parse('2026-04-22 00:00:00', 'UTC'))) {
                $slotFound = true;
                break;
            }
        }

        $this->assertTrue($slotFound);
    }

    public function test_all_day_event_on_sunday_keeps_monday_midnight_to_two_free(): void
    {
        $service = new AvailabilityService();
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
            'BEGIN:VEVENT',
            'UID:test-all-day-sunday',
            'DTSTART;VALUE=DATE:20260419',
            'DTEND;VALUE=DATE:20260420',
            'SUMMARY:All Day Sunday',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $rangeStart = Carbon::parse('2026-04-20 00:00:00', 'Europe/Budapest');
        $rangeEnd = Carbon::parse('2026-04-26 23:59:59', 'Europe/Budapest');

        $busyEvents = $service->parseIcsEvents($ics, $rangeStart, $rangeEnd, 'Europe/Budapest');

        $settings = [
            'monday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'tuesday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'wednesday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'thursday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'friday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'saturday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'sunday' => ['available' => true, 'wake' => '14:00', 'sleep' => '02:00'],
        ];

        $freeSlots = $service->computeRangeFreeSlots(
            $busyEvents,
            $settings,
            $rangeStart->copy()->subDay(),
            $rangeEnd->copy()->addDay(),
            'Europe/Budapest'
        );

        $slotFound = false;
        foreach ($freeSlots as $slot) {
            if ($slot['start']->toAtomString() === '2026-04-20T00:00:00+02:00'
                && $slot['end']->toAtomString() === '2026-04-20T02:00:00+02:00') {
                $slotFound = true;
                break;
            }
        }

        $this->assertTrue($slotFound);
    }

    // --- Sleep blocks ---

    public function test_compute_range_sleep_blocks_covers_before_wake_and_after_sleep(): void
    {
        $service = new AvailabilityService();

        $settings = [
            'monday' => ['available' => true, 'wake' => '08:00', 'sleep' => '22:00'],
        ];

        $rangeStart = Carbon::parse('2026-04-20 00:00:00', 'UTC'); // Monday
        $rangeEnd = Carbon::parse('2026-04-20 23:59:59', 'UTC');

        $sleepBlocks = $service->computeRangeSleepBlocks($settings, $rangeStart, $rangeEnd, 'UTC');

        $this->assertCount(2, $sleepBlocks);
        $this->assertSame('2026-04-20T00:00:00+00:00', $sleepBlocks[0]['start']->toAtomString());
        $this->assertSame('2026-04-20T08:00:00+00:00', $sleepBlocks[0]['end']->toAtomString());
        $this->assertSame('2026-04-20T22:00:00+00:00', $sleepBlocks[1]['start']->toAtomString());
        $this->assertSame('2026-04-20T23:59:59+00:00', $sleepBlocks[1]['end']->toAtomString());
    }

    public function test_compute_range_sleep_blocks_merges_unavailable_days_with_adjacent_gaps(): void
    {
        $service = new AvailabilityService();

        $settings = [
            'friday'   => ['available' => true, 'wake' => '09:00', 'sleep' => '01:00'],
            'saturday' => ['available' => false, 'wake' => '', 'sleep' => ''],
            'sunday'   => ['available' => false, 'wake' => '', 'sleep' => ''],
            'monday'   => ['available' => true, 'wake' => '09:00', 'sleep' => '01:00'],
        ];

        $rangeStart = Carbon::parse('2026-04-17 00:00:00', 'UTC'); // Friday
        $rangeEnd = Carbon::parse('2026-04-20 23:59:59', 'UTC');   // Monday

        $sleepBlocks = $service->computeRangeSleepBlocks($settings, $rangeStart, $rangeEnd, 'UTC');

        // Saturday's leading gap, both fully-unavailable days, and Monday's pre-wake gap
        // are all directly adjacent, so they must combine into a single block.
        $this->assertCount(2, $sleepBlocks);
        $this->assertSame('2026-04-17T00:00:00+00:00', $sleepBlocks[0]['start']->toAtomString());
        $this->assertSame('2026-04-17T09:00:00+00:00', $sleepBlocks[0]['end']->toAtomString());
        $this->assertSame('2026-04-18T01:00:00+00:00', $sleepBlocks[1]['start']->toAtomString());
        $this->assertSame('2026-04-20T09:00:00+00:00', $sleepBlocks[1]['end']->toAtomString());
    }

    public function test_merge_intervals_combines_overlapping_and_touching_but_not_disjoint(): void
    {
        $service = new AvailabilityService();

        $intervals = [
            ['start' => Carbon::parse('2026-04-20 09:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-20 10:00:00', 'UTC')],
            ['start' => Carbon::parse('2026-04-20 10:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-20 11:00:00', 'UTC')],
            ['start' => Carbon::parse('2026-04-20 10:30:00', 'UTC'), 'end' => Carbon::parse('2026-04-20 12:00:00', 'UTC')],
            ['start' => Carbon::parse('2026-04-20 14:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-20 15:00:00', 'UTC')],
        ];

        $merged = $service->mergeIntervals($intervals);

        $this->assertCount(2, $merged);
        $this->assertSame('2026-04-20T09:00:00+00:00', $merged[0]['start']->toAtomString());
        $this->assertSame('2026-04-20T12:00:00+00:00', $merged[0]['end']->toAtomString());
        $this->assertSame('2026-04-20T14:00:00+00:00', $merged[1]['start']->toAtomString());
        $this->assertSame('2026-04-20T15:00:00+00:00', $merged[1]['end']->toAtomString());
    }

    public function test_merge_event_segments_combines_back_to_back_events(): void
    {
        $service = new AvailabilityService();

        $segments = [
            ['start' => Carbon::parse('2026-04-20 09:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-20 17:00:00', 'UTC'), 'tentative' => false],
            ['start' => Carbon::parse('2026-04-20 17:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-21 02:00:00', 'UTC'), 'tentative' => false],
        ];

        $merged = $service->mergeEventSegments($segments);

        $this->assertCount(1, $merged);
        $this->assertSame('2026-04-20T09:00:00+00:00', $merged[0]['start']->toAtomString());
        $this->assertSame('2026-04-21T02:00:00+00:00', $merged[0]['end']->toAtomString());
    }

    public function test_merge_event_segments_carves_tentative_out_of_a_wrapping_confirmed_event(): void
    {
        $service = new AvailabilityService();

        // A broad confirmed event (e.g. "Me time" 14:00-02:00) wraps a tentative event
        // (17:00-21:00), followed by another confirmed event (21:00-02:00) that's a strict
        // subset of the first. Naively merging in start order would fail to combine the first
        // and third since the tentative one interrupts the sequence.
        $segments = [
            ['start' => Carbon::parse('2026-04-20 14:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-21 02:00:00', 'UTC'), 'tentative' => false],
            ['start' => Carbon::parse('2026-04-20 17:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-20 21:00:00', 'UTC'), 'tentative' => true],
            ['start' => Carbon::parse('2026-04-20 21:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-21 02:00:00', 'UTC'), 'tentative' => false],
        ];

        $merged = $service->mergeEventSegments($segments);

        $this->assertCount(3, $merged);
        $this->assertSame('2026-04-20T14:00:00+00:00', $merged[0]['start']->toAtomString());
        $this->assertSame('2026-04-20T17:00:00+00:00', $merged[0]['end']->toAtomString());
        $this->assertFalse($merged[0]['tentative']);
        $this->assertSame('2026-04-20T17:00:00+00:00', $merged[1]['start']->toAtomString());
        $this->assertSame('2026-04-20T21:00:00+00:00', $merged[1]['end']->toAtomString());
        $this->assertTrue($merged[1]['tentative']);
        $this->assertSame('2026-04-20T21:00:00+00:00', $merged[2]['start']->toAtomString());
        $this->assertSame('2026-04-21T02:00:00+00:00', $merged[2]['end']->toAtomString());
        $this->assertFalse($merged[2]['tentative']);
    }

    public function test_merge_event_segments_does_not_merge_across_differing_tentative_status(): void
    {
        $service = new AvailabilityService();

        $segments = [
            ['start' => Carbon::parse('2026-04-20 09:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-20 17:00:00', 'UTC'), 'tentative' => false],
            ['start' => Carbon::parse('2026-04-20 17:00:00', 'UTC'), 'end' => Carbon::parse('2026-04-20 18:00:00', 'UTC'), 'tentative' => true],
        ];

        $merged = $service->mergeEventSegments($segments);

        $this->assertCount(2, $merged);
        $this->assertSame('2026-04-20T17:00:00+00:00', $merged[0]['end']->toAtomString());
        $this->assertSame('2026-04-20T17:00:00+00:00', $merged[1]['start']->toAtomString());
    }

    public function test_subtract_sleep_from_events_drops_event_fully_within_sleep_block(): void
    {
        $service = new AvailabilityService();

        $events = [[
            'start'     => Carbon::parse('2026-04-20 02:00:00', 'UTC'),
            'end'       => Carbon::parse('2026-04-20 03:00:00', 'UTC'),
            'name'      => 'Late night reminder',
            'tentative' => false,
        ]];
        $sleepBlocks = [[
            'start' => Carbon::parse('2026-04-20 01:00:00', 'UTC'),
            'end'   => Carbon::parse('2026-04-20 09:00:00', 'UTC'),
        ]];

        $segments = $service->subtractSleepFromEvents($events, $sleepBlocks);

        $this->assertCount(0, $segments);
    }

    public function test_subtract_sleep_from_events_splits_event_straddling_sleep_block(): void
    {
        $service = new AvailabilityService();

        $events = [[
            'start'     => Carbon::parse('2026-04-20 22:00:00', 'UTC'),
            'end'       => Carbon::parse('2026-04-21 10:00:00', 'UTC'),
            'name'      => 'Overnight trip (?)',
            'tentative' => true,
        ]];
        $sleepBlocks = [[
            'start' => Carbon::parse('2026-04-21 01:00:00', 'UTC'),
            'end'   => Carbon::parse('2026-04-21 09:00:00', 'UTC'),
        ]];

        $segments = $service->subtractSleepFromEvents($events, $sleepBlocks);

        $this->assertCount(2, $segments);
        $this->assertSame('2026-04-20T22:00:00+00:00', $segments[0]['start']->toAtomString());
        $this->assertSame('2026-04-21T01:00:00+00:00', $segments[0]['end']->toAtomString());
        $this->assertTrue($segments[0]['tentative']);
        $this->assertSame('2026-04-21T09:00:00+00:00', $segments[1]['start']->toAtomString());
        $this->assertSame('2026-04-21T10:00:00+00:00', $segments[1]['end']->toAtomString());
        $this->assertTrue($segments[1]['tentative']);
    }

    // --- Highlight: parseIcsEvents name extraction ---

    public function test_parse_ics_events_includes_summary_as_name(): void
    {
        $service = new AvailabilityService();
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
            'BEGIN:VEVENT',
            'UID:test-named',
            'DTSTART:20260421T100000Z',
            'DTEND:20260421T110000Z',
            'SUMMARY:Team Meeting with Alice',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $rangeStart = Carbon::parse('2026-04-21 00:00:00', 'UTC');
        $rangeEnd = Carbon::parse('2026-04-21 23:59:59', 'UTC');

        $events = $service->parseIcsEvents($ics, $rangeStart, $rangeEnd, 'UTC');

        $this->assertCount(1, $events);
        $this->assertSame('Team Meeting with Alice', $events[0]['name']);
    }

    public function test_parse_ics_events_empty_summary_defaults_to_empty_string(): void
    {
        $service = new AvailabilityService();
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
            'BEGIN:VEVENT',
            'UID:test-no-summary',
            'DTSTART:20260421T100000Z',
            'DTEND:20260421T110000Z',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $rangeStart = Carbon::parse('2026-04-21 00:00:00', 'UTC');
        $rangeEnd = Carbon::parse('2026-04-21 23:59:59', 'UTC');

        $events = $service->parseIcsEvents($ics, $rangeStart, $rangeEnd, 'UTC');

        $this->assertCount(1, $events);
        $this->assertSame('', $events[0]['name']);
    }

    public function test_parse_ics_events_marks_tentative_suffix(): void
    {
        $service = new AvailabilityService();
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
            'BEGIN:VEVENT',
            'UID:test-tentative',
            'DTSTART:20260421T100000Z',
            'DTEND:20260421T110000Z',
            'SUMMARY:Team Meeting with Alice (?)',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:test-not-tentative',
            'DTSTART:20260421T120000Z',
            'DTEND:20260421T130000Z',
            'SUMMARY:Standup',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $rangeStart = Carbon::parse('2026-04-21 00:00:00', 'UTC');
        $rangeEnd = Carbon::parse('2026-04-21 23:59:59', 'UTC');

        $events = $service->parseIcsEvents($ics, $rangeStart, $rangeEnd, 'UTC');

        $this->assertCount(2, $events);
        $this->assertTrue($events[0]['tentative']);
        $this->assertFalse($events[1]['tentative']);
    }

    // --- Highlight: filterHighlightedEvents ---

    private function makeEvents(array $names): array
    {
        $events = [];
        foreach ($names as $i => $name) {
            $events[] = [
                'start' => Carbon::parse("2026-04-21 {$i}0:00:00", 'UTC'),
                'end'   => Carbon::parse("2026-04-21 {$i}1:00:00", 'UTC'),
                'name'  => $name,
            ];
        }
        return $events;
    }

    public function test_filter_highlighted_events_returns_matching_events(): void
    {
        $controller = new AvailabilityController();
        $events = $this->makeEvents(['Team meeting', 'Lunch with Bob', 'Standup']);

        $result = $this->invokePrivate($controller, 'filterHighlightedEvents', $events, ['Bob']);

        $this->assertCount(1, $result);
        $this->assertSame('Lunch with Bob', $result[0]['name']);
    }

    public function test_filter_highlighted_events_matching_is_case_sensitive(): void
    {
        $controller = new AvailabilityController();
        $events = $this->makeEvents(['Lunch with alice', 'Meeting with Alice', 'ALICE review']);

        $result = $this->invokePrivate($controller, 'filterHighlightedEvents', $events, ['Alice']);

        $this->assertCount(1, $result);
        $this->assertSame('Meeting with Alice', $result[0]['name']);
    }

    public function test_filter_highlighted_events_partial_match_returns_event(): void
    {
        $controller = new AvailabilityController();
        $events = $this->makeEvents(['SeinopSys: code review']);

        $result = $this->invokePrivate($controller, 'filterHighlightedEvents', $events, ['SeinopSys']);

        $this->assertCount(1, $result);
    }

    public function test_filter_highlighted_events_returns_empty_when_no_words(): void
    {
        $controller = new AvailabilityController();
        $events = $this->makeEvents(['Meeting with Bob']);

        $result = $this->invokePrivate($controller, 'filterHighlightedEvents', $events, []);

        $this->assertCount(0, $result);
    }

    public function test_filter_highlighted_events_returns_empty_when_no_matches(): void
    {
        $controller = new AvailabilityController();
        $events = $this->makeEvents(['Team standup', 'Lunch break']);

        $result = $this->invokePrivate($controller, 'filterHighlightedEvents', $events, ['Alice', 'Bob']);

        $this->assertCount(0, $result);
    }

    public function test_filter_highlighted_events_single_event_matched_by_multiple_words_not_duplicated(): void
    {
        $controller = new AvailabilityController();
        $events = $this->makeEvents(['Meeting with Alice and Bob']);

        $result = $this->invokePrivate($controller, 'filterHighlightedEvents', $events, ['Alice', 'Bob']);

        $this->assertCount(1, $result);
        $this->assertSame('Meeting with Alice and Bob', $result[0]['name']);
    }

    public function test_filter_highlighted_events_multiple_events_each_matched_by_different_word(): void
    {
        $controller = new AvailabilityController();
        $events = $this->makeEvents(['Lunch with Alice', 'Coffee with Bob', 'Solo work session']);

        $result = $this->invokePrivate($controller, 'filterHighlightedEvents', $events, ['Alice', 'Bob']);

        $this->assertCount(2, $result);
        $names = array_column($result, 'name');
        $this->assertContains('Lunch with Alice', $names);
        $this->assertContains('Coffee with Bob', $names);
    }
}
