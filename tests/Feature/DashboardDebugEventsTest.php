<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardDebugEventsTest extends TestCase
{
    use RefreshDatabase;

    private function makeIcs(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
        ];

        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $event['uid'];
            $lines[] = 'DTSTART:' . $event['start'];
            $lines[] = 'DTEND:' . $event['end'];
            if (!empty($event['summary'])) {
                $lines[] = 'SUMMARY:' . $event['summary'];
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        $lines[] = '';

        return implode("\r\n", $lines);
    }

    private function makeUser(string $calendarUrl = 'https://example.com/calendar.ics'): User
    {
        return User::create([
            'name'         => 'testuser',
            'email'        => 'test@example.com',
            'password'     => bcrypt('password'),
            'lang'         => 'en',
            'role'         => 'user',
            'timezone'     => 'UTC',
            'calendar_url' => $calendarUrl,
        ]);
    }

    private function seedCache(string $url, string $icsContent): void
    {
        Cache::put('ics_' . md5($url), $icsContent, now()->addMinutes(30));
    }

    public function test_debug_events_excludes_events_before_the_availability_api_cutoff(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);

        // The availability API's cutoff is "yesterday, start of day" — an event ending before
        // that is filtered from free/highlighted/unavailable/sleep. This event ends 3 days ago,
        // well past that cutoff.
        $pastStart = Carbon::now('UTC')->subDays(3)->format('Ymd\THis\Z');
        $pastEnd = Carbon::now('UTC')->subDays(3)->addHour()->format('Ymd\THis\Z');
        $futureStart = Carbon::now('UTC')->addDays(3)->format('Ymd\THis\Z');
        $futureEnd = Carbon::now('UTC')->addDays(3)->addHour()->format('Ymd\THis\Z');

        $ics = $this->makeIcs([
            ['uid' => 'past', 'start' => $pastStart, 'end' => $pastEnd, 'summary' => 'Old meeting'],
            ['uid' => 'future', 'start' => $futureStart, 'end' => $futureEnd, 'summary' => 'Upcoming meeting'],
        ]);
        $this->seedCache($calUrl, $ics);

        $start = Carbon::now('UTC')->subDays(6)->format('Y-m-d');
        $end = Carbon::now('UTC')->addDays(6)->format('Y-m-d');
        $response = $this->actingAs($user)->getJson("/dashboard/debug/events?start={$start}&end={$end}");

        $response->assertOk();
        $names = array_column($response->json(), 'name');
        $this->assertNotContains('Old meeting', $names);
        $this->assertContains('Upcoming meeting', $names);
    }

    public function test_debug_events_includes_tentative_flag(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);

        $start = Carbon::now('UTC')->addDays(3)->format('Ymd\THis\Z');
        $end = Carbon::now('UTC')->addDays(3)->addHour()->format('Ymd\THis\Z');

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => $start, 'end' => $end, 'summary' => 'Maybe drinks (?)'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->actingAs($user)->getJson('/dashboard/debug/events?start=' . Carbon::now('UTC')->format('Y-m-d'));

        $response->assertOk();
        $events = $response->json();
        $this->assertCount(1, $events);
        $this->assertTrue($events[0]['tentative']);
    }
}
