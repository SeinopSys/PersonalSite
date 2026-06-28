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
