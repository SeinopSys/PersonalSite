<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('dashboard', [
            'title' => __('global.dashboard'),
            'js'    => ['dashboard'],
            'days'  => self::DAYS,
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

        return redirect('/dashboard')->with('success', 'Settings saved.');
    }
}
