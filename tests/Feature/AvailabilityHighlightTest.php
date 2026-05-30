<?php

namespace Tests\Feature;

use App\Models\CalendarHighlightToken;
use App\Models\CalendarHighlightWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AvailabilityHighlightTest extends TestCase
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
            'name'                  => 'testuser',
            'email'                 => 'test@example.com',
            'password'              => bcrypt('password'),
            'lang'                  => 'en',
            'role'                  => 'user',
            'timezone'              => 'UTC',
            'calendar_url'          => $calendarUrl,
            'availability_settings' => [
                'monday'    => ['available' => true, 'wake' => '09:00', 'sleep' => '22:00'],
                'tuesday'   => ['available' => true, 'wake' => '09:00', 'sleep' => '22:00'],
                'wednesday' => ['available' => true, 'wake' => '09:00', 'sleep' => '22:00'],
                'thursday'  => ['available' => true, 'wake' => '09:00', 'sleep' => '22:00'],
                'friday'    => ['available' => true, 'wake' => '09:00', 'sleep' => '22:00'],
                'saturday'  => ['available' => false, 'wake' => '', 'sleep' => ''],
                'sunday'    => ['available' => false, 'wake' => '', 'sleep' => ''],
            ],
        ]);
    }

    private function seedCache(string $url, string $icsContent): void
    {
        Cache::put('ics_' . md5($url), $icsContent, now()->addMinutes(30));
    }

    public function test_availability_response_has_no_highlighted_key_without_token(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20260602T100000Z', 'end' => '20260602T110000Z', 'summary' => 'Meeting'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson('/api/availability/testuser?start=2026-06-02&end=2026-06-02');

        $response->assertOk();
        $response->assertJsonMissing(['highlighted']);
    }

    public function test_availability_response_includes_highlighted_key_with_valid_token(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => random_bytes(32),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20260602T100000Z', 'end' => '20260602T110000Z', 'summary' => 'Team standup'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2026-06-02&end=2026-06-02&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonStructure(['highlighted']);
        $response->assertJsonCount(0, 'highlighted');
    }

    public function test_availability_highlighted_events_match_words(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => random_bytes(32),
            'label'   => 'Alice',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20260602T100000Z', 'end' => '20260602T110000Z', 'summary' => 'Lunch with Alice'],
            ['uid' => 'e2', 'start' => '20260602T140000Z', 'end' => '20260602T150000Z', 'summary' => 'Team standup'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2026-06-02&end=2026-06-02&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(1, 'highlighted');
        $response->assertJsonFragment(['start' => '2026-06-02T10:00:00+00:00', 'end' => '2026-06-02T11:00:00+00:00']);
        $response->assertJsonMissing(['name' => 'Lunch with Alice']);
    }

    public function test_availability_highlighted_events_match_is_case_sensitive(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => random_bytes(32),
            'label'   => 'Alice',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20260602T100000Z', 'end' => '20260602T110000Z', 'summary' => 'Lunch with Alice'],
            ['uid' => 'e2', 'start' => '20260602T120000Z', 'end' => '20260602T130000Z', 'summary' => 'Lunch with ALICE'],
            ['uid' => 'e3', 'start' => '20260602T140000Z', 'end' => '20260602T150000Z', 'summary' => 'lunch with alice'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2026-06-02&end=2026-06-02&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(1, 'highlighted');
        // Only the exact-case match is returned; no name field exposed
        $response->assertJsonFragment(['start' => '2026-06-02T10:00:00+00:00', 'end' => '2026-06-02T11:00:00+00:00']);
        $response->assertJsonMissingExact(['name' => 'Lunch with Alice']);
    }

    public function test_availability_highlighted_events_still_block_free_slots(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => random_bytes(32),
            'label'   => 'Alice',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        // Alice blocks 10:00–11:00, leaving 09:00–10:00 and 11:00–22:00 free
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20260602T100000Z', 'end' => '20260602T110000Z', 'summary' => 'Lunch with Alice'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2026-06-02&end=2026-06-02&token={$token->token_base64}");

        $response->assertOk();

        $free = $response->json('free');
        $this->assertNotEmpty($free);

        // None of the free slots should overlap with 10:00–11:00
        foreach ($free as $slot) {
            $slotStart = strtotime($slot['start']);
            $slotEnd = strtotime($slot['end']);
            $eventStart = strtotime('2026-06-02T10:00:00+00:00');
            $eventEnd = strtotime('2026-06-02T11:00:00+00:00');

            $this->assertFalse(
                $slotStart < $eventEnd && $slotEnd > $eventStart,
                "Free slot {$slot['start']}–{$slot['end']} overlaps with highlighted event"
            );
        }
    }

    public function test_availability_invalid_token_is_silently_ignored(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $this->makeUser($calUrl);
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20260602T100000Z', 'end' => '20260602T110000Z', 'summary' => 'Meeting'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson('/api/availability/testuser?start=2026-06-02&end=2026-06-02&token=' . str_repeat('A', 43));

        $response->assertOk();
        $response->assertJsonMissing(['highlighted']);
    }

    public function test_availability_token_from_different_user_is_ignored(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $this->makeUser($calUrl);

        // Token belongs to a different user
        $otherUser = User::create([
            'name'     => 'otheruser',
            'email'    => 'other@example.com',
            'password' => bcrypt('password'),
            'lang'     => 'en',
            'role'     => 'user',
        ]);
        $token = CalendarHighlightToken::create([
            'user_id' => $otherUser->id,
            'token'   => random_bytes(32),
            'label'   => 'Test',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $otherUser->id, 'word' => 'Meeting']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20260602T100000Z', 'end' => '20260602T110000Z', 'summary' => 'Meeting'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2026-06-02&end=2026-06-02&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonMissing(['highlighted']);
    }

    public function test_availability_multiple_words_match_different_events(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => random_bytes(32),
            'label'   => 'Friends',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Bob']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20260602T100000Z', 'end' => '20260602T110000Z', 'summary' => 'Lunch with Alice'],
            ['uid' => 'e2', 'start' => '20260602T140000Z', 'end' => '20260602T150000Z', 'summary' => 'Coffee with Bob'],
            ['uid' => 'e3', 'start' => '20260602T160000Z', 'end' => '20260602T170000Z', 'summary' => 'Solo work'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2026-06-02&end=2026-06-02&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(2, 'highlighted');
        $starts = array_column($response->json('highlighted'), 'start');
        $this->assertContains('2026-06-02T10:00:00+00:00', $starts);
        $this->assertContains('2026-06-02T14:00:00+00:00', $starts);
    }
}
