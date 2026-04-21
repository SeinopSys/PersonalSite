<?php

namespace Tests\Unit;

use App\Http\Controllers\AvailabilityController;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AvailabilityControllerTest extends TestCase
{
    public function test_parse_ics_events_includes_event_that_starts_previous_day_and_overlaps_range_start(): void
    {
        $controller = new AvailabilityController();
        $parseIcsEvents = new \ReflectionMethod(AvailabilityController::class, 'parseIcsEvents');
        $parseIcsEvents->setAccessible(true);

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

        $events = $parseIcsEvents->invoke($controller, $ics, $rangeStart, $rangeEnd, 'UTC');

        $this->assertCount(1, $events);
        $this->assertSame('2026-04-20T23:00:00+00:00', $events[0]['start']->toAtomString());
        $this->assertSame('2026-04-21T01:00:00+00:00', $events[0]['end']->toAtomString());
    }
}
