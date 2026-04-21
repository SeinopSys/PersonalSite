<?php

namespace Tests\Unit;

use App\Http\Controllers\AvailabilityController;
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
        $controller = new AvailabilityController();
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

        $events = $this->invokePrivate($controller, 'parseIcsEvents', $ics, $rangeStart, $rangeEnd, 'UTC');

        $this->assertCount(1, $events);
        $this->assertSame('2026-04-20T23:00:00+00:00', $events[0]['start']->toAtomString());
        $this->assertSame('2026-04-21T01:00:00+00:00', $events[0]['end']->toAtomString());
    }

    public function test_parse_ics_events_includes_event_that_starts_day_after_range_end(): void
    {
        $controller = new AvailabilityController();
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

        $events = $this->invokePrivate($controller, 'parseIcsEvents', $ics, $rangeStart, $rangeEnd, 'UTC');

        $this->assertCount(1, $events);
        $this->assertSame('2026-04-22T00:30:00+00:00', $events[0]['start']->toAtomString());
        $this->assertSame('2026-04-22T01:30:00+00:00', $events[0]['end']->toAtomString());
    }

    public function test_compute_range_free_slots_honors_extended_range_for_cross_midnight_sleep_window(): void
    {
        $controller = new AvailabilityController();

        $busyEvents = [[
            'start' => Carbon::parse('2026-04-22 00:30:00', 'UTC'),
            'end' => Carbon::parse('2026-04-22 01:00:00', 'UTC'),
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

        $freeSlots = $this->invokePrivate(
            $controller,
            'computeRangeFreeSlots',
            $busyEvents,
            $settings,
            $rangeStart->copy()->subDay(),
            $rangeEnd->copy()->addDay(),
            'UTC'
        );

        $slotFound = false;
        foreach ($freeSlots as $slot) {
            if ($slot['start']->toAtomString() === '2026-04-22T00:00:00+00:00'
                && $slot['end']->toAtomString() === '2026-04-22T00:30:00+00:00') {
                $slotFound = true;
                break;
            }
        }

        $this->assertTrue($slotFound);
    }

    public function test_all_day_event_on_sunday_keeps_monday_midnight_to_two_free(): void
    {
        $controller = new AvailabilityController();
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

        $busyEvents = $this->invokePrivate($controller, 'parseIcsEvents', $ics, $rangeStart, $rangeEnd, 'Europe/Budapest');

        $settings = [
            'monday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'tuesday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'wednesday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'thursday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'friday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'saturday' => ['available' => true, 'wake' => '09:00', 'sleep' => '02:00'],
            'sunday' => ['available' => true, 'wake' => '14:00', 'sleep' => '02:00'],
        ];

        $freeSlots = $this->invokePrivate(
            $controller,
            'computeRangeFreeSlots',
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
}
