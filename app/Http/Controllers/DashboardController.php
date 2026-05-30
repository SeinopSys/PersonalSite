<?php

namespace App\Http\Controllers;

use App\Models\CalendarHighlightToken;
use App\Models\CalendarHighlightWord;
use App\Util\Permission;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Sabre\VObject\Reader;

class DashboardController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        $data = [
            'title'  => __('global.dashboard'),
            'js'     => ['dashboard'],
            'days'   => self::DAYS,
        ];

        if (Permission::Sufficient('developer')) {
            $data['highlights'] = $user->highlightTokens()->with('words')->get();
            $data['isDeveloper'] = true;
        }

        return view('dashboard', $data);
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'calendar_url' => 'nullable|url|max:2048',
            'timezone'     => 'nullable|timezone:all',
            'settings'     => 'nullable|array',
        ]);

        $availabilitySettings = [];
        foreach (self::DAYS as $day) {
            $daySetting = $request->input("settings.$day");
            $available = !empty($daySetting['available']);
            $availabilitySettings[$day] = [
                'available' => $available,
                'wake' => $available ? ($daySetting['wake'] ?? '') : '',
                'sleep' => $available ? ($daySetting['sleep'] ?? '') : '',
            ];
        }

        $user = Auth::user();
        $user->calendar_url = $validated['calendar_url'] ?? null;
        $user->timezone = $validated['timezone'] ?? null;
        $user->availability_settings = $availabilitySettings;
        $user->save();

        return redirect('/dashboard')->with('success', 'Settings saved.');
    }

    public function debugEvents(Request $request): JsonResponse
    {

        $user = Auth::user();
        if (!$user->calendar_url) {
            return response()->json(['error' => 'No calendar configured'], 404);
        }

        $tz = $user->timezone ?? 'UTC';
        $start = $request->input('start');
        $end = $request->input('end');

        if ($start) {
            $rangeStart = Carbon::parse($start, $tz)->startOfDay();
            $rangeEnd = $end
                ? Carbon::parse($end, $tz)->endOfDay()
                : $rangeStart->copy()->addDays(6)->endOfDay();
        } else {
            $rangeStart = Carbon::now($tz)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $rangeEnd = Carbon::now($tz)->endOfWeek(Carbon::SUNDAY)->endOfDay();
        }

        try {
            $cacheKey = 'ics_' . md5($user->calendar_url);
            $icsContent = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($user) {
                $client = new Client(['timeout' => 10]);
                return (string)$client->get($user->calendar_url)->getBody();
            });
        } catch (\Exception) {
            return response()->json(['error' => 'Failed to fetch calendar data'], 503);
        }

        $calendar = Reader::read($icsContent);
        $expandStart = $rangeStart->copy()->utc()->subDay();
        $expandEnd = $rangeEnd->copy()->utc()->addDay();
        $calendar = $calendar->expand(
            \DateTimeImmutable::createFromInterface($expandStart),
            \DateTimeImmutable::createFromInterface($expandEnd)
        );

        $events = [];
        if (isset($calendar->VEVENT)) {
            foreach ($calendar->VEVENT as $event) {
                $valueType = strtoupper((string)($event->DTSTART['VALUE'] ?? ''));
                if ($valueType === 'DATE') {
                    $eventStart = Carbon::parse((string)$event->DTSTART->getValue(), $tz)->startOfDay();
                } else {
                    $eventStart = Carbon::instance($event->DTSTART->getDateTime())->setTimezone($tz);
                }

                if (isset($event->DTEND)) {
                    $valueType = strtoupper((string)($event->DTEND['VALUE'] ?? ''));
                    if ($valueType === 'DATE') {
                        $eventEnd = Carbon::parse((string)$event->DTEND->getValue(), $tz)->startOfDay();
                    } else {
                        $eventEnd = Carbon::instance($event->DTEND->getDateTime())->setTimezone($tz);
                    }
                } else {
                    $eventEnd = $eventStart->copy()->addHour();
                }

                $events[] = [
                    'start' => $eventStart->toAtomString(),
                    'end'   => $eventEnd->toAtomString(),
                    'name'  => isset($event->SUMMARY) ? (string)$event->SUMMARY : '',
                ];
            }
        }

        usort($events, fn($a, $b) => $a['start'] <=> $b['start']);

        return response()->json($events);
    }

    public function storeHighlight(Request $request)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
        ]);

        CalendarHighlightToken::create([
            'user_id' => Auth::id(),
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => $validated['label'] ?? null,
        ]);

        return redirect('/dashboard#highlights')->with('success', 'Highlight token created.');
    }

    public function updateHighlight(Request $request, string $id)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
        ]);

        $token = CalendarHighlightToken::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $token->label = $validated['label'] ?? null;
        $token->save();

        return redirect('/dashboard#highlights')->with('success', 'Label updated.');
    }

    public function regenerateHighlight(string $id)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $token = CalendarHighlightToken::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $token->token = CalendarHighlightToken::generateToken();
        $token->save();

        return redirect('/dashboard#highlights')->with('success', 'Token regenerated.');
    }

    public function destroyHighlight(string $id)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        CalendarHighlightToken::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail()
            ->delete();

        return redirect('/dashboard#highlights')->with('success', 'Highlight token deleted.');
    }

    public function storeHighlightWord(Request $request, string $tokenId)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $validated = $request->validate([
            'word' => 'required|string|max:255',
        ]);

        $token = CalendarHighlightToken::where('id', $tokenId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $word = $validated['word'];
        $errorBag = 'words_' . $tokenId;

        if (CalendarHighlightWord::where('user_id', Auth::id())->where('word', $word)->exists()) {
            return back()
                ->withErrors(['word' => "\"$word\" is already used in one of your highlight groups."], $errorBag)
                ->withInput();
        }

        CalendarHighlightWord::create([
            'token_id' => $token->id,
            'user_id'  => Auth::id(),
            'word'     => $word,
        ]);

        return redirect('/dashboard#highlights')->with('success', 'Word added.');
    }

    public function destroyHighlightWord(string $tokenId, string $wordId)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $token = CalendarHighlightToken::where('id', $tokenId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        CalendarHighlightWord::where('id', $wordId)
            ->where('token_id', $token->id)
            ->firstOrFail()
            ->delete();

        return redirect('/dashboard#highlights')->with('success', 'Word removed.');
    }
}
