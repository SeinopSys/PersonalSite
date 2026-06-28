<?php

namespace App\Http\Controllers;

use App\Models\CalendarHighlightToken;
use App\Models\CalendarHighlightWord;
use App\Services\AvailabilityService;
use App\Util\Core;
use App\Util\Permission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $data = ['title' => __('global.dashboard'), 'js' => ['dashboard']];

        // Availability stats
        if ($user->calendar_url) {
            try {
                $service  = new AvailabilityService();
                $tz       = $user->timezone ?? 'UTC';
                $settings = $user->availability_settings ?? [];
                $now      = Carbon::now($tz);

                $past30Start = $now->copy()->subDays(29)->startOfDay();
                $weekEnd     = $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
                $rangeStart  = $past30Start;
                $rangeEnd    = $weekEnd;

                $ics    = $service->fetchIcs($user->calendar_url);
                $events = $service->parseIcsEvents($ics, $rangeStart, $rangeEnd, $tz);

                $subArgs = [$events, $settings, $rangeStart->copy()->subDay(), $rangeEnd->copy()->addDay(), $tz];

                $freeSlots      = $service->computeRangeFreeSlots(...$subArgs);
                $workMinsByDate = $service->computeFilteredMinutesByDate(
                    $events, $settings,
                    $rangeStart->copy()->subDay(),
                    $rangeEnd->copy()->addDay(),
                    $tz,
                    fn($e) => str_contains($e['name'], 'Work')
                );

                // Group free slots by day — computeRangeFreeSlots processes one day at a time,
                // so each slot's start date corresponds exactly to its day's window.
                $slotsByDate = [];
                foreach ($freeSlots as $slot) {
                    $slotsByDate[$slot['start']->format('Y-m-d')][] = $slot;
                }

                $dowNames = AvailabilityService::DAY_NAMES;

                // Today (HH:mm free + HH:mm work, plus % for bars)
                $todayKey     = $now->format('Y-m-d');
                $todayWindow  = $service->dayWindowMinutes($dowNames[$now->dayOfWeek], $settings);
                $todayFreeMin = $service->sumSlotMinutes($slotsByDate[$todayKey] ?? []);
                $todayWorkMin = $workMinsByDate[$todayKey] ?? 0;
                $todaySleepMin = 1440 - $todayWindow;
                if ($todayWindow > 0) {
                    $todayBusyMin = max(0, $todayWindow - $todayFreeMin - $todayWorkMin);
                    $data['todayFreeFormatted']  = sprintf('%d:%02d', intdiv($todayFreeMin, 60), $todayFreeMin % 60);
                    $data['todayFreePct']        = min(100, (int)round($todayFreeMin / $todayWindow * 100));
                    $data['todayWorkFormatted']  = sprintf('%d:%02d', intdiv($todayWorkMin, 60), $todayWorkMin % 60);
                    $data['todayWorkPct']        = min(100, (int)round($todayWorkMin / $todayWindow * 100));
                    $data['todayBusyFormatted']  = sprintf('%d:%02d', intdiv($todayBusyMin, 60), $todayBusyMin % 60);
                    $data['todayBusyPct']        = min(100, (int)round($todayBusyMin / $todayWindow * 100));
                    $data['todayWorkBarPct']     = (int)round($todayWorkMin / 1440 * 100);
                    $data['todayBusyBarPct']     = (int)round($todayBusyMin / 1440 * 100);
                    $data['todaySleepBarPct']    = (int)round($todaySleepMin / 1440 * 100);
                    $data['todaySleepFormatted'] = sprintf('%d:%02d', intdiv($todaySleepMin, 60), $todaySleepMin % 60);
                    $data['todaySleepPct']       = (int)round($todaySleepMin / 1440 * 100);
                } else {
                    $data['todayFreeFormatted']  = null;
                    $data['todayFreePct']        = null;
                    $data['todayWorkFormatted']  = null;
                    $data['todayWorkPct']        = null;
                    $data['todayBusyFormatted']  = null;
                    $data['todayBusyPct']        = null;
                    $data['todayWorkBarPct']     = 0;
                    $data['todayBusyBarPct']     = 0;
                    $data['todaySleepBarPct']    = 100;
                    $data['todaySleepFormatted'] = '24:00';
                    $data['todaySleepPct']       = 100;
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
                $data['weekFreeFormatted']  = sprintf('%d:%02d', intdiv($weekFreeMin, 60), $weekFreeMin % 60);
                $data['weekWorkFormatted']  = sprintf('%d:%02d', intdiv($weekWorkMin, 60), $weekWorkMin % 60);
                $data['weekBusyFormatted']  = sprintf('%d:%02d', intdiv($weekBusyMin, 60), $weekBusyMin % 60);
                $data['weekSleepFormatted'] = sprintf('%d:%02d', intdiv($weekSleepMin, 60), $weekSleepMin % 60);
                $data['weekFreePct'] = $weekWindow > 0
                    ? min(100, (int)round($weekFreeMin / $weekWindow * 100))
                    : null;
                $data['weekWorkPct'] = $weekWindow > 0
                    ? min(100, (int)round($weekWorkMin / $weekWindow * 100))
                    : null;
                $data['weekBusyPct'] = $weekWindow > 0
                    ? min(100, (int)round($weekBusyMin / $weekWindow * 100))
                    : null;
                $data['weekSleepPct'] = (int)round($weekSleepMin / $weekTotalMin * 100);
                $data['weekWorkBarPct']  = (int)round($weekWorkMin / $weekTotalMin * 100);
                $data['weekBusyBarPct']  = (int)round($weekBusyMin / $weekTotalMin * 100);
                $data['weekSleepBarPct'] = (int)round($weekSleepMin / $weekTotalMin * 100);

                // Past 30 days — aggregate stats
                $past30FreeMin = 0;
                $past30WorkMin = 0;
                $past30Window  = 0;
                for ($i = 29; $i >= 0; $i--) {
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
                $past30TotalMin = 30 * 1440;
                $past30SleepMin = $past30TotalMin - $past30Window;
                $data['past30FreeFormatted']  = sprintf('%d:%02d', intdiv($past30FreeMin, 60), $past30FreeMin % 60);
                $data['past30WorkFormatted']  = sprintf('%d:%02d', intdiv($past30WorkMin, 60), $past30WorkMin % 60);
                $data['past30BusyFormatted']  = sprintf('%d:%02d', intdiv($past30BusyMin, 60), $past30BusyMin % 60);
                $data['past30SleepFormatted'] = sprintf('%d:%02d', intdiv($past30SleepMin, 60), $past30SleepMin % 60);
                $data['past30FreePct'] = $past30Window > 0
                    ? min(100, (int)round($past30FreeMin / $past30Window * 100))
                    : null;
                $data['past30WorkPct'] = $past30Window > 0
                    ? min(100, (int)round($past30WorkMin / $past30Window * 100))
                    : null;
                $data['past30BusyPct'] = $past30Window > 0
                    ? min(100, (int)round($past30BusyMin / $past30Window * 100))
                    : null;
                $data['past30SleepPct'] = (int)round($past30SleepMin / $past30TotalMin * 100);
                $data['past30WorkBarPct']  = (int)round($past30WorkMin / $past30TotalMin * 100);
                $data['past30BusyBarPct']  = (int)round($past30BusyMin / $past30TotalMin * 100);
                $data['past30SleepBarPct'] = (int)round($past30SleepMin / $past30TotalMin * 100);

                // Friends toplist — sum event minutes per highlight token over the full data range
                $highlights   = $user->highlightTokens()->with('words')->get();
                $friendsData  = [];
                foreach ($highlights as $token) {
                    $words = $token->words->pluck('word')->toArray();
                    if (empty($words)) {
                        continue;
                    }
                    $tokenWords    = $words;
                    $minutesByDate = $service->computeFilteredMinutesByDate(
                        $events, $settings,
                        $rangeStart->copy()->subDay(),
                        $rangeEnd->copy()->addDay(),
                        $tz,
                        fn($e) => (bool)array_filter($tokenWords, fn($w) => str_contains($e['name'], $w))
                    );
                    $totalMin = array_sum($minutesByDate);
                    if ($totalMin > 0) {
                        $friendsData[] = ['label' => $token->label ?? 'Unnamed', 'minutes' => $totalMin];
                    }
                }
                usort($friendsData, fn($a, $b) => $b['minutes'] <=> $a['minutes']);
                $data['friendsData'] = $friendsData;

            } catch (\Exception) {
                $data['availabilityFetchError'] = true;
            }
        }

        // Uploads stats
        $imageUpload = $user->imageUpload()->first();
        $data['uploadingEnabled'] = !empty($imageUpload);
        if ($data['uploadingEnabled']) {
            $usedBytes  = (int)$user->uploads()->sum('size');
            $quotaBytes = (int)config('app.upload_quota_bytes');
            $data['usedSpace']    = Core::ReadableFilesize($usedBytes);
            $data['quotaSpace']   = Core::ReadableFilesize($quotaBytes);
            $data['usedPct']      = $quotaBytes > 0
                ? min(100, (int)round($usedBytes / $quotaBytes * 100))
                : 0;
            $data['recentUploads'] = $user->uploads()
                ->orderByDesc('uploaded_at')
                ->limit(4)
                ->get();
        }

        return view('dashboard', $data);
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
            $data['highlights'] = $user->highlightTokens()->with('words')->get();
            $data['isDeveloper'] = true;
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
            'label' => 'nullable|string|max:255',
        ]);

        CalendarHighlightToken::create([
            'user_id' => Auth::id(),
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => $validated['label'] ?? null,
        ]);

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

        return redirect('/availability#highlights')->with('success', 'Label updated.');
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

        return redirect('/availability#highlights')->with('success', 'Word added.');
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

        return redirect('/availability#highlights')->with('success', 'Word removed.');
    }
}
