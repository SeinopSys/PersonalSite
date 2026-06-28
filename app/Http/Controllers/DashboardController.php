<?php

namespace App\Http\Controllers;

use App\Models\CalendarHighlightToken;
use App\Models\CalendarHighlightWord;
use App\Services\AvailabilityService;
use App\Util\Core;
use App\Util\JSON;
use App\Util\Permission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    private const PAST_DAYS = 90;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $data = ['title' => __('global.dashboard'), 'js' => ['dashboard']];

        $data['hasCalendar'] = !empty($user->calendar_url);
        $data['pastDays'] = self::PAST_DAYS;
        if ($data['hasCalendar']) {
            $data['highlightLabels'] = $user->highlightTokens()
                ->orderBy('label')
                ->get(['label'])
                ->map(fn($t) => $t->label ?? 'Unnamed')
                ->toArray();
        }

        $imageUpload = $user->imageUpload()->first();
        $data['uploadingEnabled'] = !empty($imageUpload);

        return view('dashboard', $data);
    }

    public function statsAvailability(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->calendar_url) {
            return response()->json(['error' => 'no_calendar']);
        }

        try {
            $service  = new AvailabilityService();
            $tz       = $user->timezone ?? 'UTC';
            $settings = $user->availability_settings ?? [];
            $now      = Carbon::now($tz);

            $past30Start = $now->copy()->subDays(self::PAST_DAYS - 1)->startOfDay();
            $weekEnd     = $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $rangeStart  = $past30Start;
            $rangeEnd    = $weekEnd;

            $ics    = $service->fetchIcs($user->calendar_url);
            $events = $service->parseIcsEvents($ics, $rangeStart, $rangeEnd, $tz);

            $freeSlots      = $service->computeRangeFreeSlots($events, $settings, $rangeStart->copy()->subDay(), $rangeEnd->copy()->addDay(), $tz);
            $workMinsByDate = $service->computeFilteredMinutesByDate(
                $events, $settings,
                $rangeStart->copy()->subDay(),
                $rangeEnd->copy()->addDay(),
                $tz,
                fn($e) => str_contains($e['name'], 'Work')
            );

            $slotsByDate = [];
            foreach ($freeSlots as $slot) {
                $slotsByDate[$slot['start']->format('Y-m-d')][] = $slot;
            }

            $dowNames = AvailabilityService::DAY_NAMES;
            $hhmm = fn(int $m) => sprintf('%d:%02d', intdiv($m, 60), $m % 60);

            // Today
            $todayKey      = $now->format('Y-m-d');
            $todayWindow   = $service->dayWindowMinutes($dowNames[$now->dayOfWeek], $settings);
            $todayFreeMin  = $service->sumSlotMinutes($slotsByDate[$todayKey] ?? []);
            $todayWorkMin  = $workMinsByDate[$todayKey] ?? 0;
            $todaySleepMin = 1440 - $todayWindow;
            if ($todayWindow > 0) {
                $todayBusyMin = max(0, $todayWindow - $todayFreeMin - $todayWorkMin);
                $todayRow = [
                    'title'      => 'Today',
                    'notAvail'   => false,
                    'sleepLabel' => $hhmm($todaySleepMin),
                    'sleepPct'   => (int)round($todaySleepMin / 1440 * 100),
                    'sleepBarPct'=> (int)round($todaySleepMin / 1440 * 100),
                    'workLabel'  => $hhmm($todayWorkMin),
                    'workPct'    => min(100, (int)round($todayWorkMin / $todayWindow * 100)),
                    'workBarPct' => (int)round($todayWorkMin / 1440 * 100),
                    'busyLabel'  => $hhmm($todayBusyMin),
                    'busyPct'    => min(100, (int)round($todayBusyMin / $todayWindow * 100)),
                    'busyBarPct' => (int)round($todayBusyMin / 1440 * 100),
                    'freeLabel'  => $hhmm($todayFreeMin),
                    'freePct'    => min(100, (int)round($todayFreeMin / $todayWindow * 100)),
                ];
            } else {
                $todayRow = [
                    'title'      => 'Today',
                    'notAvail'   => true,
                    'sleepLabel' => '24:00', 'sleepPct' => 100, 'sleepBarPct' => 100,
                    'workLabel'  => null, 'workPct' => 0, 'workBarPct' => 0,
                    'busyLabel'  => null, 'busyPct' => 0, 'busyBarPct' => 0,
                    'freeLabel'  => null, 'freePct' => null,
                ];
            }

            // This week (Mon–Sun)
            $weekStart   = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $weekFreeMin = 0;
            $weekWorkMin = 0;
            $weekWindow  = 0;
            $weekDay     = $weekStart->copy();
            while ($weekDay->lte($weekEnd)) {
                $dk           = $weekDay->format('Y-m-d');
                $weekWindow  += $service->dayWindowMinutes($dowNames[$weekDay->dayOfWeek], $settings);
                $weekFreeMin += $service->sumSlotMinutes($slotsByDate[$dk] ?? []);
                $weekWorkMin += $workMinsByDate[$dk] ?? 0;
                $weekDay->addDay();
            }
            $weekBusyMin  = max(0, $weekWindow - $weekFreeMin - $weekWorkMin);
            $weekTotalMin = 7 * 1440;
            $weekSleepMin = $weekTotalMin - $weekWindow;
            $weekRow = [
                'title'      => 'This week',
                'notAvail'   => $weekWindow === 0,
                'sleepLabel' => $hhmm($weekSleepMin),
                'sleepPct'   => (int)round($weekSleepMin / $weekTotalMin * 100),
                'sleepBarPct'=> (int)round($weekSleepMin / $weekTotalMin * 100),
                'workLabel'  => $hhmm($weekWorkMin),
                'workPct'    => $weekWindow > 0 ? min(100, (int)round($weekWorkMin / $weekWindow * 100)) : 0,
                'workBarPct' => (int)round($weekWorkMin / $weekTotalMin * 100),
                'busyLabel'  => $hhmm($weekBusyMin),
                'busyPct'    => $weekWindow > 0 ? min(100, (int)round($weekBusyMin / $weekWindow * 100)) : 0,
                'busyBarPct' => (int)round($weekBusyMin / $weekTotalMin * 100),
                'freeLabel'  => $hhmm($weekFreeMin),
                'freePct'    => $weekWindow > 0 ? min(100, (int)round($weekFreeMin / $weekWindow * 100)) : null,
            ];

            // Past N days
            $past30FreeMin = 0;
            $past30WorkMin = 0;
            $past30Window  = 0;
            for ($i = self::PAST_DAYS - 1; $i >= 0; $i--) {
                $day    = $now->copy()->subDays($i);
                $dk     = $day->format('Y-m-d');
                $window = $service->dayWindowMinutes($dowNames[$day->dayOfWeek], $settings);
                $past30Window += $window;
                if ($window > 0) {
                    $past30FreeMin += $service->sumSlotMinutes($slotsByDate[$dk] ?? []);
                    $past30WorkMin += $workMinsByDate[$dk] ?? 0;
                }
            }
            $past30BusyMin  = max(0, $past30Window - $past30FreeMin - $past30WorkMin);
            $past30TotalMin = self::PAST_DAYS * 1440;
            $past30SleepMin = $past30TotalMin - $past30Window;
            $past30Row = [
                'title'      => 'Past ' . self::PAST_DAYS . ' days',
                'notAvail'   => $past30Window === 0,
                'sleepLabel' => $hhmm($past30SleepMin),
                'sleepPct'   => (int)round($past30SleepMin / $past30TotalMin * 100),
                'sleepBarPct'=> (int)round($past30SleepMin / $past30TotalMin * 100),
                'workLabel'  => $hhmm($past30WorkMin),
                'workPct'    => $past30Window > 0 ? min(100, (int)round($past30WorkMin / $past30Window * 100)) : 0,
                'workBarPct' => (int)round($past30WorkMin / $past30TotalMin * 100),
                'busyLabel'  => $hhmm($past30BusyMin),
                'busyPct'    => $past30Window > 0 ? min(100, (int)round($past30BusyMin / $past30Window * 100)) : 0,
                'busyBarPct' => (int)round($past30BusyMin / $past30TotalMin * 100),
                'freeLabel'  => $hhmm($past30FreeMin),
                'freePct'    => $past30Window > 0 ? min(100, (int)round($past30FreeMin / $past30Window * 100)) : null,
            ];

            // Highlights toplist
            $highlightTokens = $user->highlightTokens()->with('words')->get();
            $highlightsData  = [];
            foreach ($highlightTokens as $token) {
                $words = $token->words->pluck('word')->toArray();
                if (empty($words)) {
                    $totalMin = 0;
                } else {
                    $tokenWords    = $words;
                    $minutesByDate = $service->computeFilteredMinutesByDate(
                        $events, [],
                        $rangeStart->copy()->subDay(),
                        $rangeEnd->copy()->addDay(),
                        $tz,
                        fn($e) => (bool)array_filter($tokenWords, fn($w) => str_contains($e['name'], $w))
                    );
                    $totalMin = array_sum($minutesByDate);
                }
                $highlightsData[] = ['label' => $token->label ?? 'Unnamed', 'minutes' => $totalMin, 'archived' => $token->archived];
            }
            usort($highlightsData, fn($a, $b) => $b['minutes'] <=> $a['minutes']);

            $topHighlights    = array_values(array_slice(array_filter($highlightsData, fn($h) => $h['minutes'] > 0), 0, 10));
            $noTimeHighlights = array_values(array_filter($highlightsData, fn($h) => $h['minutes'] === 0 && !$h['archived']));

            return response()->json([
                'rows'              => [$todayRow, $weekRow, $past30Row],
                'highlights'        => $topHighlights,
                'highlightsNoTime'  => $noTimeHighlights,
            ]);
        } catch (\Exception) {
            return response()->json(['error' => 'fetch_failed']);
        }
    }

    public function statsUploads(): JsonResponse
    {
        $user = Auth::user();
        $imageUpload = $user->imageUpload()->first();
        if (empty($imageUpload)) {
            return response()->json(['error' => 'not_enabled']);
        }

        $usedBytes  = (int)$user->uploads()->sum('size');
        $quotaBytes = (int)config('app.upload_quota_bytes');

        $uploads = $user->uploads()
            ->orderByDesc('uploaded_at')
            ->limit(4)
            ->get()
            ->map(fn($u) => [
                'preview' => $u->host . '/' . $u->filename . 'p.png',
                'full'    => $u->host . '/' . $u->filename . '.' . $u->extension,
                'name'    => $u->orig_filename,
            ])
            ->values()
            ->toArray();

        return response()->json([
            'usedSpace'  => Core::ReadableFilesize($usedBytes),
            'quotaSpace' => Core::ReadableFilesize($quotaBytes),
            'usedPct'    => $quotaBytes > 0 ? min(100, (int)round($usedBytes / $quotaBytes * 100)) : 0,
            'uploads'    => $uploads,
        ]);
    }

    public function availability(Request $request)
    {
        $user = Auth::user();
        $data = [
            'title' => __('global.availability'),
            'js'    => ['availability'],
            'days'  => self::DAYS,
        ];

        if (Permission::Sufficient('developer')) {
            $sort = $request->input('sort', 'label');
            $dir  = $request->input('dir', 'asc');
            if (!in_array($sort, ['created_at', 'label'])) $sort = 'created_at';
            if (!in_array($dir, ['asc', 'desc'])) $dir = 'asc';

            $data['highlights']  = $user->highlightTokens()->with('words')->orderBy($sort, $dir)->get();
            $data['isDeveloper'] = true;
            $data['sort']        = $sort;
            $data['dir']         = $dir;
        }

        return view('availability', $data);
    }

    public function account(Request $request)
    {
        $user = Auth::user();
        return view('account', [
            'title'          => __('global.account'),
            'twoFactorSetup' => $user->hasTwoFactorEnabled()
                ? null
                : TwoFactorAuthController::pendingSetup($request, $user),
        ]);
    }

    public function saveProfile(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->name  = $validated['name'];
        $user->email = strtolower($validated['email']);
        $user->save();

        return redirect('/account')->with('success', __('dashboard.profile-saved'));
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

        return redirect('/availability')->with('success', 'Settings saved.');
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

        $service = new AvailabilityService();

        try {
            $icsContent = $service->fetchIcs($user->calendar_url);
        } catch (\Exception) {
            return response()->json(['error' => 'Failed to fetch calendar data'], 503);
        }

        $events = $service->parseIcsEvents($icsContent, $rangeStart, $rangeEnd, $tz);

        return response()->json(array_map(fn($e) => [
            'start' => $e['start']->toAtomString(),
            'end'   => $e['end']->toAtomString(),
            'name'  => $e['name'],
        ], $events));
    }

    public function storeHighlight(Request $request)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $userId = Auth::id();
        $label  = $validated['label'];

        $token = CalendarHighlightToken::create([
            'user_id' => $userId,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => $label,
        ]);

        if ($label && !CalendarHighlightWord::where('user_id', $userId)->where('word', $label)->exists()) {
            CalendarHighlightWord::create([
                'token_id' => $token->id,
                'user_id'  => $userId,
                'word'     => $label,
            ]);
        }

        return redirect('/availability#highlights')->with('success', 'Highlight token created.');
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

        return redirect('/availability#highlights')->with('success', 'Label updated.')->with('open_highlight', $id);
    }

    public function regenerateHighlight(string $id)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $token = CalendarHighlightToken::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $token->token = CalendarHighlightToken::generateToken();
        $token->save();

        return redirect('/availability#highlights')->with('success', 'Token regenerated.');
    }

    public function destroyHighlight(string $id)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        CalendarHighlightToken::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail()
            ->delete();

        return redirect('/availability#highlights')->with('success', 'Highlight token deleted.');
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

        return redirect('/availability#highlights')->with('success', 'Word added.')->with('open_highlight', $tokenId);
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

        return redirect('/availability#highlights')->with('success', 'Word removed.')->with('open_highlight', $tokenId);
    }

    public function exportHighlights()
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $user = Auth::user();
        $highlights = $user->highlightTokens()->with('words')->get();
        $data = $highlights->map(fn($ht) => [
            'label'      => $ht->label,
            'token'      => $ht->token_base64,
            'created_at' => $ht->created_at->toIso8601String(),
            'words'      => $ht->words->pluck('word')->values()->toArray(),
        ])->toArray();

        return response()->streamDownload(
            function () use ($data) {
                echo JSON::Encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            },
            'highlights.json',
            ['Content-Type' => 'application/json']
        );
    }

    public function archiveHighlight(string $id)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $token = CalendarHighlightToken::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $token->archived = !$token->archived;
        $token->save();

        $msg = $token->archived ? 'Token archived.' : 'Token unarchived.';
        return redirect('/availability#highlights')->with('success', $msg)->with('open_highlight', $id);
    }

    public function importHighlights(Request $request)
    {
        abort_unless(Permission::Sufficient('developer'), 403);

        $request->validate(['file' => 'required|file|mimes:json,txt|max:1024']);

        $contents = file_get_contents($request->file('file')->getRealPath());
        $data = JSON::Decode($contents);

        if (!is_array($data)) {
            return back()->withErrors(['file' => 'Invalid JSON: expected an array of highlight objects.']);
        }

        $userId = Auth::id();
        $imported = 0;
        $skipped = 0;
        $seenWords = [];

        // Validate all items before making any changes
        $valid = [];
        foreach ($data as $item) {
            if (!is_array($item) || empty($item['token']) || !is_string($item['token'])) {
                $skipped++;
                continue;
            }
            if (!CalendarHighlightToken::isValidBase64Url($item['token'])) {
                $skipped++;
                continue;
            }
            $bytes = CalendarHighlightToken::decodeBase64Url($item['token']);
            if ($bytes === null) {
                $skipped++;
                continue;
            }
            $valid[] = array_merge($item, ['_bytes' => $bytes]);
        }

        // Replace: wipe existing tokens then insert from file
        DB::transaction(function () use ($userId, $valid, &$imported, &$seenWords) {
            CalendarHighlightToken::where('user_id', $userId)->delete();

            foreach ($valid as $item) {
                $label = isset($item['label']) && is_string($item['label'])
                    ? substr($item['label'], 0, 255)
                    : null;

                $createdAt = null;
                if (!empty($item['created_at']) && is_string($item['created_at'])) {
                    try { $createdAt = Carbon::parse($item['created_at']); } catch (\Exception) {}
                }

                $token = CalendarHighlightToken::create([
                    'user_id'    => $userId,
                    'token'      => $item['_bytes'],
                    'label'      => $label,
                    'created_at' => $createdAt ?? now(),
                ]);

                foreach ($item['words'] ?? [] as $word) {
                    if (!is_string($word) || $word === '') {
                        continue;
                    }
                    $word = substr($word, 0, 255);
                    if (!isset($seenWords[$word])) {
                        $seenWords[$word] = true;
                        CalendarHighlightWord::create([
                            'token_id' => $token->id,
                            'user_id'  => $userId,
                            'word'     => $word,
                        ]);
                    }
                }

                $imported++;
            }
        });

        return redirect('/availability#highlights')
            ->with('success', "Import complete: $imported created, $skipped skipped.");
    }
}
