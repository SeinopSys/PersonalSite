@extends('layouts.container')

@section('panel-body')
    @php $user = Auth::user(); @endphp

    <h2>{{ __('global.dashboard') }}</h2>

    @if(!$user->hasTwoFactorEnabled())
        <div class="alert alert-warning">
            <x-fa icon="exclamation-triangle" first></x-fa>
            Two-factor authentication is not enabled on your account.
            <a href="/account" class="alert-link">Enable it on the Account page</a> to improve your security.
        </div>
    @endif

    <h3>{{ __('dashboard.greeting', ['name' => $user->name]) }}</h3>
    <ul>
        <li><strong>{{ __('global.role') }}:</strong> {{ \App\Util\Permission::LocalizedRoleName($user->role) }}</li>
        <li><strong>UUID:</strong> {{ $user->id }}</li>
        <li><strong>Stored language:</strong> {{ strtoupper($user->lang) }}</li>
        <li><a href="/docs/api">API documentation</a> (<a href="/docs/api.json">JSON</a>)</li>
    </ul>

    <div class="row g-4 mt-0">

        {{-- Availability --}}
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('global.availability') }}</h5>
                    <a href="/availability" class="btn btn-sm btn-outline-secondary">Manage</a>
                </div>
                <div class="card-body">
                    @if(!$user->calendar_url)
                        <p class="text-muted mb-0">
                            No calendar URL configured.
                            <a href="/availability">Set it up on the Availability page</a> to see your free/busy stats here.
                        </p>
                    @elseif(isset($availabilityFetchError))
                        <p class="text-danger mb-0">
                            <x-fa icon="exclamation-circle" first></x-fa>Failed to fetch calendar data.
                        </p>
                    @else
                        {{-- Today --}}
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold">Today</span>
                                @if($todayFreeFormatted === null)
                                    <span class="text-muted">Not available</span>
                                @else
                                    <span>
                                        {{ $todayFreeFormatted }} ({{ $todayFreePct }}%) free
                                        @if(($todayWorkPct ?? 0) > 0)
                                            &middot; <span class="text-primary">{{ $todayWorkFormatted }} ({{ $todayWorkPct }}%) work</span>
                                        @endif
                                        @if(($todayBusyPct ?? 0) > 0)
                                            &middot; <span class="text-danger">{{ $todayBusyFormatted }} ({{ $todayBusyPct }}%) busy</span>
                                        @endif
                                        @if(($todaySleepPct ?? 0) > 0)
                                            &middot; <span class="text-secondary">{{ $todaySleepFormatted }} ({{ $todaySleepPct }}%) sleep</span>
                                        @endif
                                    </span>
                                @endif
                            </div>
                            <div class="progress" style="height:8px">
                                @if(($todayWorkBarPct ?? 0) > 0)
                                    <div class="progress-bar bg-primary" style="width:{{ $todayWorkBarPct }}%"
                                         title="{{ $todayWorkPct ?? 0 }}% work"></div>
                                @endif
                                @if(($todayBusyBarPct ?? 0) > 0)
                                    <div class="progress-bar bg-danger" style="width:{{ $todayBusyBarPct }}%"
                                         title="{{ $todayBusyPct ?? 0 }}% busy"></div>
                                @endif
                                @if(($todaySleepBarPct ?? 0) > 0)
                                    <div class="progress-bar bg-secondary" style="width:{{ $todaySleepBarPct }}%"
                                         title="sleep"></div>
                                @endif
                            </div>
                        </div>

                        {{-- This week --}}
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold">This week</span>
                                @if($weekFreePct === null)
                                    <span class="text-muted">Not available</span>
                                @else
                                    <span>
                                        {{ $weekFreePct }}% free
                                        @if(($weekWorkPct ?? 0) > 0)
                                            &middot; <span class="text-primary">{{ $weekWorkPct }}% work</span>
                                        @endif
                                        @if(($weekBusyPct ?? 0) > 0)
                                            &middot; <span class="text-danger">{{ $weekBusyPct }}% busy</span>
                                        @endif
                                        @if(($weekSleepPct ?? 0) > 0)
                                            &middot; <span class="text-secondary">{{ $weekSleepPct }}% sleep</span>
                                        @endif
                                    </span>
                                @endif
                            </div>
                            <div class="progress" style="height:8px">
                                @if(($weekWorkBarPct ?? 0) > 0)
                                    <div class="progress-bar bg-primary" style="width:{{ $weekWorkBarPct }}%"
                                         title="{{ $weekWorkPct ?? 0 }}% work"></div>
                                @endif
                                @if(($weekBusyBarPct ?? 0) > 0)
                                    <div class="progress-bar bg-danger" style="width:{{ $weekBusyBarPct }}%"
                                         title="{{ $weekBusyPct ?? 0 }}% busy"></div>
                                @endif
                                @if(($weekSleepBarPct ?? 0) > 0)
                                    <div class="progress-bar bg-secondary" style="width:{{ $weekSleepBarPct }}%"
                                         title="sleep"></div>
                                @endif
                            </div>
                        </div>

                        {{-- Past 30 days --}}
                        <div class="mb-4">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold">Past 30 days</span>
                                @if($past30FreePct === null)
                                    <span class="text-muted">Not available</span>
                                @else
                                    <span>
                                        {{ $past30FreePct }}% free
                                        @if(($past30WorkPct ?? 0) > 0)
                                            &middot; <span class="text-primary">{{ $past30WorkPct }}% work</span>
                                        @endif
                                        @if(($past30BusyPct ?? 0) > 0)
                                            &middot; <span class="text-danger">{{ $past30BusyPct }}% busy</span>
                                        @endif
                                        @if(($past30SleepPct ?? 0) > 0)
                                            &middot; <span class="text-secondary">{{ $past30SleepPct }}% sleep</span>
                                        @endif
                                    </span>
                                @endif
                            </div>
                            <div class="progress" style="height:8px">
                                @if(($past30WorkBarPct ?? 0) > 0)
                                    <div class="progress-bar bg-primary" style="width:{{ $past30WorkBarPct }}%"
                                         title="{{ $past30WorkPct ?? 0 }}% work"></div>
                                @endif
                                @if(($past30BusyBarPct ?? 0) > 0)
                                    <div class="progress-bar bg-danger" style="width:{{ $past30BusyBarPct }}%"
                                         title="{{ $past30BusyPct ?? 0 }}% busy"></div>
                                @endif
                                @if(($past30SleepBarPct ?? 0) > 0)
                                    <div class="progress-bar bg-secondary" style="width:{{ $past30SleepBarPct }}%"
                                         title="sleep"></div>
                                @endif
                            </div>
                        </div>

                        {{-- Past 30 days bar chart --}}
                        <div class="small text-muted mb-1">Busiest days — past 30 days</div>
                        <div style="display:flex;align-items:stretch;height:56px;gap:1px">
                            @foreach($past30Data as $d)
                                @php
                                    $pct     = $d['busyPct'];
                                    $wpct    = $d['workPct'];
                                    $unavail = $pct === null;
                                    // All columns are 56px; scale everything relative to full 24h (1440 min)
                                    $sleepPx = $unavail ? 56 : (int)round((1440 - $d['_window']) / 1440 * 56);
                                @endphp
                                @if($unavail)
                                    <div style="flex:1;min-width:1px;background:#6c757d;border-radius:2px 2px 0 0"
                                         title="{{ $d['dow'] }} {{ $d['date'] }}: not available"></div>
                                @else
                                    @php
                                        $busyPx    = (int)round(max(0, $d['_busyMin'] ?? 0) / 1440 * 56);
                                        $workPx    = (int)round(($d['_workMin'] ?? 0) / 1440 * 56);
                                        $otherBusy = max(0, $busyPx - $workPx);
                                        $label     = "{$d['dow']} {$d['date']}: {$pct}% busy" . ($wpct > 0 ? " ({$wpct}% work)" : '');
                                    @endphp
                                    <div style="flex:1;min-width:1px;position:relative" title="{{ $label }}">
                                        {{-- Sleep band at top --}}
                                        @if($sleepPx > 0)
                                            <div style="position:absolute;top:0;left:0;right:0;height:{{ $sleepPx }}px;background:#6c757d;border-radius:2px 2px 0 0"></div>
                                        @endif
                                        {{-- Other-busy band above work --}}
                                        @if($otherBusy > 0)
                                            <div style="position:absolute;bottom:{{ $workPx }}px;left:0;right:0;height:{{ $otherBusy }}px;background:#dc3545"></div>
                                        @endif
                                        {{-- Work band at the bottom --}}
                                        @if($workPx > 0)
                                            <div style="position:absolute;bottom:0;left:0;right:0;height:{{ $workPx }}px;background:#0d6efd"></div>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        {{-- Friends toplist --}}
                        @if(!empty($friendsData))
                            @php
                                $formatFriendMin = function(int $m): string {
                                    if ($m >= 1440) {
                                        $d = intdiv($m, 1440);
                                        $r = $m % 1440;
                                        return $d.'d '.sprintf('%d:%02d', intdiv($r, 60), $r % 60);
                                    }
                                    return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
                                };
                            @endphp
                            <div class="small text-muted mb-1 mt-3">Time with friends — past 30 days</div>
                            <ol class="list-unstyled mb-0 small">
                                @foreach($friendsData as $friend)
                                    <li class="d-flex justify-content-between">
                                        <span>{{ $friend['label'] }}</span>
                                        <span class="text-muted ms-2">{{ $formatFriendMin($friend['minutes']) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Uploads --}}
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('global.uploads') }}</h5>
                    <a href="/uploads" class="btn btn-sm btn-outline-secondary">Manage</a>
                </div>
                <div class="card-body">
                    @if(!$uploadingEnabled)
                        <p class="text-muted mb-0">
                            Uploading is not enabled for your account.
                            Contact the site owner to request access.
                        </p>
                    @else
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">Space used</span>
                            <span>{{ $usedSpace }} / {{ $quotaSpace }}</span>
                        </div>
                        <div class="progress mb-1" style="height:10px" role="progressbar"
                             aria-valuenow="{{ $usedPct }}" aria-valuemin="0" aria-valuemax="100">
                            @php
                                $barClass = $usedPct >= 90 ? 'bg-danger' : ($usedPct >= 70 ? 'bg-warning' : 'bg-primary');
                            @endphp
                            <div class="progress-bar {{ $barClass }}" style="width:{{ $usedPct }}%"></div>
                        </div>
                        <div class="small text-muted">{{ $usedPct }}% of quota used</div>
                    @endif
                </div>
            </div>
        </div>

    </div>
@endsection
