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

    public function test_availability_without_token_is_unauthorized(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $this->makeUser($calUrl);
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Meeting'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson('/api/availability/testuser?start=2030-06-03&end=2030-06-03');

        $response->assertUnauthorized();
    }

    public function test_availability_response_includes_highlighted_key_with_valid_token(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Team standup'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

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
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Alice',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Lunch with Alice'],
            ['uid' => 'e2', 'start' => '20300603T140000Z', 'end' => '20300603T150000Z', 'summary' => 'Team standup'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(1, 'highlighted');
        $response->assertJsonFragment(['start' => '2030-06-03T10:00:00+00:00', 'end' => '2030-06-03T11:00:00+00:00']);
        $response->assertJsonMissing(['name' => 'Lunch with Alice']);
    }

    public function test_availability_tentative_highlighted_event_includes_tentative_flag(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Alice',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Lunch with Alice (?)'],
            ['uid' => 'e2', 'start' => '20300603T140000Z', 'end' => '20300603T150000Z', 'summary' => 'Meeting with Alice'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(2, 'highlighted');

        $highlighted = $response->json('highlighted');
        $tentativeSlot = collect($highlighted)->firstWhere('start', '2030-06-03T10:00:00+00:00');
        $confirmedSlot = collect($highlighted)->firstWhere('start', '2030-06-03T14:00:00+00:00');

        $this->assertTrue($tentativeSlot['tentative']);
        $this->assertArrayNotHasKey('tentative', $confirmedSlot);
    }

    public function test_availability_response_includes_sleep_key(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonStructure(['sleep']);
    }

    public function test_availability_sleep_merges_weekend_with_adjacent_wake_sleep_gaps(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([]);
        $this->seedCache($calUrl, $ics);

        // 2030-06-07 is a Friday, 2030-06-10 is the following Monday.
        $response = $this->getJson("/api/availability/testuser?start=2030-06-07&end=2030-06-10&token={$token->token_base64}");

        $response->assertOk();
        $sleep = $response->json('sleep');

        // Friday's post-sleep gap, both weekend days (marked fully unavailable), and Monday's
        // pre-wake gap are all directly adjacent, so they must combine into a single block.
        $mergedBlock = collect($sleep)->first(fn($s) =>
            $s['start'] === '2030-06-07T22:00:00+00:00' && $s['end'] === '2030-06-10T09:00:00+00:00'
        );
        $this->assertNotNull($mergedBlock, 'Expected a merged sleep block spanning Friday 22:00 through Monday 09:00');
    }

    public function test_availability_unavailable_excludes_event_fully_within_sleep_hours(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        // Default settings sleep from 22:00, so this event falls entirely within sleep hours.
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T230000Z', 'end' => '20300604T000000Z', 'summary' => 'Late night reminder'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(0, 'unavailable');
    }

    public function test_availability_unavailable_clips_event_straddling_sleep_hours(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        // Default settings sleep from 22:00 to 09:00, so this event should be clipped to 21:00–22:00.
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T210000Z', 'end' => '20300603T230000Z', 'summary' => 'Team standup'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(1, 'unavailable');
        $this->assertSame('2030-06-03T21:00:00+00:00', $response->json('unavailable.0.start'));
        $this->assertSame('2030-06-03T22:00:00+00:00', $response->json('unavailable.0.end'));
    }

    public function test_availability_default_nap_event_is_treated_as_sleep(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        // 14:00-15:00 is well within the default 09:00-22:00 wake window, so this only becomes
        // a sleep block because its name matches the default nap event name.
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T140000Z', 'end' => '20300603T150000Z', 'summary' => 'Taking a nap'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(0, 'unavailable');
        $sleep = $response->json('sleep');
        $napBlock = collect($sleep)->first(fn($s) =>
            $s['start'] === '2030-06-03T14:00:00+00:00' && $s['end'] === '2030-06-03T15:00:00+00:00'
        );
        $this->assertNotNull($napBlock, 'Expected the nap event to appear as a sleep block');
    }

    public function test_availability_custom_nap_event_name_is_configurable_per_user(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $user->nap_event_name = 'Power nap';
        $user->save();

        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            // Matches the default name, but this user configured a custom one, so it should NOT count as sleep.
            ['uid' => 'e1', 'start' => '20300603T140000Z', 'end' => '20300603T150000Z', 'summary' => 'Taking a nap'],
            // Matches the custom name, so it should count as sleep.
            ['uid' => 'e2', 'start' => '20300603T160000Z', 'end' => '20300603T170000Z', 'summary' => 'Power nap'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(1, 'unavailable');
        $this->assertSame('2030-06-03T14:00:00+00:00', $response->json('unavailable.0.start'));

        $sleep = $response->json('sleep');
        $napBlock = collect($sleep)->first(fn($s) =>
            $s['start'] === '2030-06-03T16:00:00+00:00' && $s['end'] === '2030-06-03T17:00:00+00:00'
        );
        $this->assertNotNull($napBlock, 'Expected the custom-named nap event to appear as a sleep block');
    }

    public function test_availability_unavailable_lists_all_busy_events_with_tentative_flag(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Lunch with Alice (?)'],
            ['uid' => 'e2', 'start' => '20300603T140000Z', 'end' => '20300603T150000Z', 'summary' => 'Team standup'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(2, 'unavailable');

        $unavailable = $response->json('unavailable');
        $tentativeSlot = collect($unavailable)->firstWhere('start', '2030-06-03T10:00:00+00:00');
        $confirmedSlot = collect($unavailable)->firstWhere('start', '2030-06-03T14:00:00+00:00');

        $this->assertTrue($tentativeSlot['tentative']);
        $this->assertArrayNotHasKey('tentative', $confirmedSlot);
    }

    public function test_availability_unavailable_excludes_dnd_event_when_bypassed(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id'    => $user->id,
            'token'      => CalendarHighlightToken::generateToken(),
            'label'      => 'Friend',
            'bypass_dnd' => true,
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Do not disturb'],
            ['uid' => 'e2', 'start' => '20300603T140000Z', 'end' => '20300603T150000Z', 'summary' => 'Team standup'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(1, 'unavailable');
        $this->assertSame('2030-06-03T14:00:00+00:00', $response->json('unavailable.0.start'));
    }

    public function test_availability_highlighted_events_match_is_case_sensitive(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Alice',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Lunch with Alice'],
            ['uid' => 'e2', 'start' => '20300603T120000Z', 'end' => '20300603T130000Z', 'summary' => 'Lunch with ALICE'],
            ['uid' => 'e3', 'start' => '20300603T140000Z', 'end' => '20300603T150000Z', 'summary' => 'lunch with alice'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(1, 'highlighted');
        // Only the exact-case match is returned; no name field exposed
        $response->assertJsonFragment(['start' => '2030-06-03T10:00:00+00:00', 'end' => '2030-06-03T11:00:00+00:00']);
        $response->assertJsonMissingExact(['name' => 'Lunch with Alice']);
    }

    public function test_availability_highlighted_events_still_block_free_slots(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Alice',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        // Alice blocks 10:00–11:00, leaving 09:00–10:00 and 11:00–22:00 free
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Lunch with Alice'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();

        $free = $response->json('free');
        $this->assertNotEmpty($free);

        // None of the free slots should overlap with 10:00–11:00
        foreach ($free as $slot) {
            $slotStart = strtotime($slot['start']);
            $slotEnd = strtotime($slot['end']);
            $eventStart = strtotime('2030-06-03T10:00:00+00:00');
            $eventEnd = strtotime('2030-06-03T11:00:00+00:00');

            $this->assertFalse(
                $slotStart < $eventEnd && $slotEnd > $eventStart,
                "Free slot {$slot['start']}–{$slot['end']} overlaps with highlighted event"
            );
        }
    }

    public function test_availability_invalid_token_is_unauthorized(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $this->makeUser($calUrl);
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Meeting'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson('/api/availability/testuser?start=2030-06-03&end=2030-06-03&token=' . str_repeat('A', 43));

        $response->assertUnauthorized();
    }

    public function test_availability_token_from_different_user_is_unauthorized(): void
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
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Test',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $otherUser->id, 'word' => 'Meeting']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Meeting'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertUnauthorized();
    }

    public function test_availability_highlighted_events_in_the_past_are_excluded(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friends',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Lunch with Alice'],
            ['uid' => 'e2', 'start' => '20200101T100000Z', 'end' => '20200101T110000Z', 'summary' => 'Old lunch with Alice'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(1, 'highlighted');
        $this->assertEquals('2030-06-03T10:00:00+00:00', $response->json('highlighted.0.start'));
    }

    public function test_availability_multiple_words_match_different_events(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friends',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Bob']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Lunch with Alice'],
            ['uid' => 'e2', 'start' => '20300603T140000Z', 'end' => '20300603T150000Z', 'summary' => 'Coffee with Bob'],
            ['uid' => 'e3', 'start' => '20300603T160000Z', 'end' => '20300603T170000Z', 'summary' => 'Solo work'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $response->assertJsonCount(2, 'highlighted');
        $starts = array_column($response->json('highlighted'), 'start');
        $this->assertContains('2030-06-03T10:00:00+00:00', $starts);
        $this->assertContains('2030-06-03T14:00:00+00:00', $starts);
    }

    public function test_dnd_event_blocks_free_slots_by_default(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id' => $user->id,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => 'Friend',
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Do not disturb'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $free = $response->json('free');
        foreach ($free as $slot) {
            $slotStart = strtotime($slot['start']);
            $slotEnd = strtotime($slot['end']);
            $eventStart = strtotime('2030-06-03T10:00:00+00:00');
            $eventEnd = strtotime('2030-06-03T11:00:00+00:00');

            $this->assertFalse(
                $slotStart < $eventEnd && $slotEnd > $eventStart,
                "Free slot {$slot['start']}–{$slot['end']} overlaps with DND event despite no bypass"
            );
        }
    }

    public function test_dnd_event_is_shown_as_free_for_bypassing_token(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id'    => $user->id,
            'token'      => CalendarHighlightToken::generateToken(),
            'label'      => 'Friend',
            'bypass_dnd' => true,
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Do not disturb'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $free = $response->json('free');
        $eventStart = strtotime('2030-06-03T10:00:00+00:00');
        $eventEnd = strtotime('2030-06-03T11:00:00+00:00');

        $coversEvent = false;
        foreach ($free as $slot) {
            if (strtotime($slot['start']) <= $eventStart && strtotime($slot['end']) >= $eventEnd) {
                $coversEvent = true;
            }
        }
        $this->assertTrue($coversEvent, 'DND event should be reported as free for a bypassing token');
    }

    public function test_dnd_event_name_is_matched_exactly(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $token = CalendarHighlightToken::create([
            'user_id'    => $user->id,
            'token'      => CalendarHighlightToken::generateToken(),
            'label'      => 'Friend',
            'bypass_dnd' => true,
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        // Not an exact match to the default "Do not disturb" name — should stay busy even with bypass.
        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Do not disturb please'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $free = $response->json('free');
        $eventStart = strtotime('2030-06-03T10:00:00+00:00');
        $eventEnd = strtotime('2030-06-03T11:00:00+00:00');

        foreach ($free as $slot) {
            $this->assertFalse(
                strtotime($slot['start']) < $eventEnd && strtotime($slot['end']) > $eventStart,
                'Non-exact-match event should still block free slots even with bypass enabled'
            );
        }
    }

    public function test_dnd_event_name_is_configurable_per_user(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);
        $user->dnd_event_name = 'Focus time';
        $user->save();

        $token = CalendarHighlightToken::create([
            'user_id'    => $user->id,
            'token'      => CalendarHighlightToken::generateToken(),
            'label'      => 'Friend',
            'bypass_dnd' => true,
        ]);
        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Focus time'],
        ]);
        $this->seedCache($calUrl, $ics);

        $response = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$token->token_base64}");

        $response->assertOk();
        $free = $response->json('free');
        $eventStart = strtotime('2030-06-03T10:00:00+00:00');
        $eventEnd = strtotime('2030-06-03T11:00:00+00:00');

        $coversEvent = false;
        foreach ($free as $slot) {
            if (strtotime($slot['start']) <= $eventStart && strtotime($slot['end']) >= $eventEnd) {
                $coversEvent = true;
            }
        }
        $this->assertTrue($coversEvent, 'Custom DND event name should be bypassable too');
    }

    /**
     * The raw ICS feed is cached (Cache::remember, keyed only by calendar URL), but the DND
     * bypass filter must be applied fresh per request from the token used — never baked into
     * a shared cache entry — or one token's bypass setting would leak into another's response.
     */
    public function test_shared_ics_cache_does_not_leak_bypass_between_tokens(): void
    {
        $calUrl = 'https://example.com/calendar.ics';
        $user = $this->makeUser($calUrl);

        $bypassToken = CalendarHighlightToken::create([
            'user_id'    => $user->id,
            'token'      => CalendarHighlightToken::generateToken(),
            'label'      => 'Bypasses DND',
            'bypass_dnd' => true,
        ]);
        CalendarHighlightWord::create(['token_id' => $bypassToken->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $normalToken = CalendarHighlightToken::create([
            'user_id'    => $user->id,
            'token'      => CalendarHighlightToken::generateToken(),
            'label'      => 'Does not bypass DND',
            'bypass_dnd' => false,
        ]);
        CalendarHighlightWord::create(['token_id' => $normalToken->id, 'user_id' => $user->id, 'word' => 'Bob']);

        $ics = $this->makeIcs([
            ['uid' => 'e1', 'start' => '20300603T100000Z', 'end' => '20300603T110000Z', 'summary' => 'Do not disturb'],
        ]);
        $this->seedCache($calUrl, $ics);

        $eventStart = strtotime('2030-06-03T10:00:00+00:00');
        $eventEnd = strtotime('2030-06-03T11:00:00+00:00');
        $overlaps = fn(array $slot) => strtotime($slot['start']) < $eventEnd && strtotime($slot['end']) > $eventStart;
        $covers = fn(array $slot) => strtotime($slot['start']) <= $eventStart && strtotime($slot['end']) >= $eventEnd;

        // Fire the non-bypassing token first so the ICS cache entry is populated before the
        // bypassing token's request — if bypass leaked via that shared cache, this would fail.
        $normalResponse = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$normalToken->token_base64}");
        $normalResponse->assertOk();
        $normalFree = $normalResponse->json('free');
        foreach ($normalFree as $slot) {
            $this->assertFalse($overlaps($slot), 'Non-bypassing token must still see the DND event as busy');
        }

        $bypassResponse = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$bypassToken->token_base64}");
        $bypassResponse->assertOk();
        $bypassFree = $bypassResponse->json('free');
        $this->assertTrue(
            collect($bypassFree)->contains($covers),
            'Bypassing token must see the DND event as free despite the shared ICS cache'
        );

        // Re-request with the non-bypassing token again, after the bypassing one has run, to
        // rule out the reverse leak direction.
        $normalResponseAgain = $this->getJson("/api/availability/testuser?start=2030-06-03&end=2030-06-03&token={$normalToken->token_base64}");
        $normalResponseAgain->assertOk();
        foreach ($normalResponseAgain->json('free') as $slot) {
            $this->assertFalse($overlaps($slot), 'Non-bypassing token must not inherit the other token\'s bypass from cache');
        }
    }
}
