@extends('layouts.container')

@section('panel-body')
    @php
        $user = Auth::user();
        $availSettings = $user->availability_settings ?? [];
        $defaults = [
            'monday'    => ['available' => true,  'wake' => '09:00', 'sleep' => '01:00'],
            'tuesday'   => ['available' => true,  'wake' => '09:00', 'sleep' => '01:00'],
            'wednesday' => ['available' => true,  'wake' => '09:00', 'sleep' => '01:00'],
            'thursday'  => ['available' => true,  'wake' => '09:00', 'sleep' => '01:00'],
            'friday'    => ['available' => true,  'wake' => '09:00', 'sleep' => '02:00'],
            'saturday'  => ['available' => false, 'wake' => '',      'sleep' => ''],
            'sunday'    => ['available' => false, 'wake' => '',      'sleep' => ''],
        ];
        $daySetting = function(string $day) use ($availSettings, $defaults) {
            return array_merge($defaults[$day], $availSettings[$day] ?? []);
        };
    @endphp

    <h2>{{ __('global.dashboard') }}</h2>

    <h3>{{ __('dashboard.greeting', ['name' => $user->name]) }}</h3>
    <ul>
        <li><strong>{{ __('global.role') }}:</strong> {{ \App\Util\Permission::LocalizedRoleName($user->role) }}</li>
        <li><strong>UUID:</strong> {{ $user->id }}</li>
        <li><strong>Stored language:</strong> {{ strtoupper($user->lang) }}</li>
    </ul>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <h3>Availability settings</h3>
    <form method="POST" action="/dashboard/settings">
        @csrf

        <div class="mb-3">
            <label for="user-timezone" class="form-label fw-semibold">Timezone</label>
            <select class="form-select" id="user-timezone" name="timezone">
                <option value="">UTC (default)</option>
                @php
                    $tzGroups = collect(\DateTimeZone::listIdentifiers())
                        ->groupBy(fn($tz) => str_contains($tz, '/') ? explode('/', $tz)[0] : 'Other');
                    $current = old('timezone', $user->timezone ?? '');
                @endphp
                @foreach($tzGroups as $region => $zones)
                    <optgroup label="{{ $region }}">
                        @foreach($zones as $tz)
                            <option value="{{ $tz }}" {{ $current === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label for="calendar-url" class="form-label fw-semibold">Calendar URL (ICS)</label>
            <input type="url"
                   class="form-control @error('calendar_url') is-invalid @enderror"
                   id="calendar-url"
                   name="calendar_url"
                   value="{{ old('calendar_url', $user->calendar_url) }}"
                   placeholder="https://…/calendar.ics">
            @error('calendar_url')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">A public ICS feed URL. Free slots will be queried via <code>GET /api/availability/{{ $user->name }}?start=YYYY-MM-DD&amp;end=YYYY-MM-DD</code>. Both parameters are optional and default to the current week.</div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Sleep schedule</label>
            <div class="form-text mb-2">
                Set when you wake up and go to sleep each day. Free slots are shown between wake and sleep times.
                Sleep times past midnight (e.g. 01:00) are treated as the following day.
            </div>
            <table class="table table-sm align-middle w-auto">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Available</th>
                        <th>Wake up</th>
                        <th>Go to sleep</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($days as $day)
                        @php $s = $daySetting($day); @endphp
                        <tr>
                            <td class="pe-3">{{ ucfirst($day) }}</td>
                            <td class="text-center">
                                <input type="checkbox"
                                       class="form-check-input day-available-check"
                                       name="settings[{{ $day }}][available]"
                                       value="1"
                                       data-day="{{ $day }}"
                                       {{ old("settings.$day.available", $s['available']) ? 'checked' : '' }}>
                            </td>
                            <td class="px-2">
                                <input type="time"
                                       class="form-control form-control-sm day-time-input"
                                       name="settings[{{ $day }}][wake]"
                                       value="{{ old("settings.$day.wake", $s['wake']) }}"
                                       style="width:8rem"
                                       {{ !$s['available'] ? 'disabled' : '' }}>
                            </td>
                            <td class="px-2">
                                <input type="time"
                                       class="form-control form-control-sm day-time-input"
                                       name="settings[{{ $day }}][sleep]"
                                       value="{{ old("settings.$day.sleep", $s['sleep']) }}"
                                       style="width:8rem"
                                       {{ !$s['available'] ? 'disabled' : '' }}>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
    </form>

    <h3 class="mt-4">Test availability</h3>
    <div class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <label for="avail-start" class="form-label mb-1">From</label>
            <input type="date" class="form-control" id="avail-start">
        </div>
        <div class="col-auto">
            <label for="avail-end" class="form-label mb-1">To</label>
            <input type="date" class="form-control" id="avail-end">
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-secondary" id="avail-fetch">Fetch</button>
        </div>
    </div>
    <div id="avail-calendar" class="border rounded overflow-hidden" data-username="{{ $user->name }}"></div>
@endsection
