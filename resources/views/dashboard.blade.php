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
                            <a href="/availability">Set it up on the Availability page</a> to see your free/busy stats
                            here.
                        </p>
                    @elseif(isset($availabilityFetchError))
                        <p class="text-danger mb-0">
                            <x-fa icon="exclamation-circle" first></x-fa>
                            Failed to fetch calendar data.
                        </p>
                    @else
                        @php
                            $fmt = fn(?string $t, ?int $p = null) => $t !== null ? ($p !== null ? "$t ($p%)" : $t) : "$p%";
                            $availRows = [
                                [
                                    'title'       => 'Today',
                                    'notAvail'    => $todayFreeFormatted === null,
                                    'workLabel'   => $fmt($todayWorkFormatted ?? null),
                                    'busyLabel'   => $fmt($todayBusyFormatted ?? null),
                                    'sleepLabel'  => $fmt($todaySleepFormatted ?? null),
                                    'freeLabel'   => $fmt($todayFreeFormatted ?? null),
                                    'workPct'     => $todayWorkPct ?? 0,
                                    'busyPct'     => $todayBusyPct ?? 0,
                                    'sleepPct'    => $todaySleepPct ?? 0,
                                    'workBarPct'  => $todayWorkBarPct ?? 0,
                                    'busyBarPct'  => $todayBusyBarPct ?? 0,
                                    'sleepBarPct' => $todaySleepBarPct ?? 0,
                                ],
                                [
                                    'title'       => 'This week',
                                    'notAvail'    => $weekFreePct === null,
                                    'workLabel'   => $fmt(null, $weekWorkPct ?? 0),
                                    'busyLabel'   => $fmt(null, $weekBusyPct ?? 0),
                                    'sleepLabel'  => $fmt(null, $weekSleepPct ?? 0),
                                    'freeLabel'   => $fmt(null, $weekFreePct ?? 0),
                                    'workPct'     => $weekWorkPct ?? 0,
                                    'busyPct'     => $weekBusyPct ?? 0,
                                    'sleepPct'    => $weekSleepPct ?? 0,
                                    'workBarPct'  => $weekWorkBarPct ?? 0,
                                    'busyBarPct'  => $weekBusyBarPct ?? 0,
                                    'sleepBarPct' => $weekSleepBarPct ?? 0,
                                ],
                                [
                                    'title'       => 'Past 30 days',
                                    'notAvail'    => $past30FreePct === null,
                                    'workLabel'   => $fmt(null, $past30WorkPct ?? 0),
                                    'busyLabel'   => $fmt(null, $past30BusyPct ?? 0),
                                    'sleepLabel'  => $fmt(null, $past30SleepPct ?? 0),
                                    'freeLabel'   => $fmt(null, $past30FreePct ?? 0),
                                    'workPct'     => $past30WorkPct ?? 0,
                                    'busyPct'     => $past30BusyPct ?? 0,
                                    'sleepPct'    => $past30SleepPct ?? 0,
                                    'workBarPct'  => $past30WorkBarPct ?? 0,
                                    'busyBarPct'  => $past30BusyBarPct ?? 0,
                                    'sleepBarPct' => $past30SleepBarPct ?? 0,
                                ],
                            ];
                        @endphp
                        @foreach($availRows as $row)
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-semibold">{{ $row['title'] }}</span>
                                    @if($row['notAvail'])
                                        <span class="text-muted">Not available</span>
                                    @else
                                        <span>
                                            @if($row['sleepPct'] > 0)
                                                <span>{{ $row['sleepLabel'] }} sleep</span> &middot;
                                            @endif
                                            @if($row['workPct'] > 0)
                                                <span class="text-primary">{{ $row['workLabel'] }} work</span> &middot;
                                            @endif
                                            @if($row['busyPct'] > 0)
                                                <span class="text-danger">{{ $row['busyLabel'] }} busy</span> &middot;
                                            @endif
                                            <span class="text-secondary">{{ $row['freeLabel'] }} free</span>
                                        </span>
                                    @endif
                                </div>
                                <div class="progress" style="height:8px">
                                    @if($row['sleepBarPct'] > 0)
                                        <div class="progress-bar bg-secondary" style="width:{{ $row['sleepBarPct'] }}%"
                                             title="sleep"></div>
                                    @endif
                                    @if($row['workBarPct'] > 0)
                                        <div class="progress-bar bg-primary" style="width:{{ $row['workBarPct'] }}%"
                                             title="{{ $row['workPct'] }}% work"></div>
                                    @endif
                                    @if($row['busyBarPct'] > 0)
                                        <div class="progress-bar bg-danger" style="width:{{ $row['busyBarPct'] }}%"
                                             title="{{ $row['busyPct'] }}% busy"></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach

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
                            <div class="small fw-semibold mb-1 mt-3">Top highlights, past 30 days</div>
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
                        @if($recentUploads->isNotEmpty())
                            <div class="small fw-semibold mb-2 mt-3">Recent uploads</div>
                            <div class="d-flex justify-content-between gap-2">
                                @foreach($recentUploads as $upload)
                                    <a href="#" class="dashboard-upload-preview"
                                       data-full="{{ $upload->host }}/{{ $upload->filename }}.{{ $upload->extension }}"
                                       data-name="{{ $upload->orig_filename }}"
                                       title="{{ $upload->orig_filename }}"
                                       style="flex:1;min-width:0;aspect-ratio:1;display:block">
                                        <img src="{{ $upload->host }}/{{ $upload->filename }}p.png"
                                             alt="{{ $upload->orig_filename }}"
                                             style="width:100%;height:100%;object-fit:cover;border-radius:4px">
                                    </a>
                                @endforeach
                            </div>

                            {{-- Preview modal --}}
                            <div class="modal fade" id="uploadPreviewModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title text-truncate" id="uploadPreviewModalLabel"></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-0 text-center bg-dark">
                                            <img id="uploadPreviewImg" src="" alt=""
                                                 style="max-width:100%;max-height:80vh;object-fit:contain">
                                        </div>
                                        <div class="modal-footer">
                                            <a id="uploadPreviewOpen" href="#" target="_blank" class="btn btn-primary">Open
                                                full image</a>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                Close
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

    </div>
@endsection

