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
                    @if(!$hasCalendar)
                        <p class="text-muted mb-0">
                            No calendar URL configured.
                            <a href="/availability">Set it up on the Availability page</a> to see your free/busy stats
                            here.
                        </p>
                    @else
                        <div id="avail-stats">
                            {{-- Skeleton rows --}}
                            @foreach(['Today', 'This week', 'Past ' . $pastDays . ' days'] as $i => $rowTitle)
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="fw-semibold">{{ $rowTitle }}</span>
                                        <span class="placeholder-glow"><span class="placeholder" style="width:{{ 11 + ($i * 2) }}rem"></span></span>
                                    </div>
                                    <div class="progress" style="height:8px">
                                        <div class="progress-bar bg-secondary progress-bar-striped progress-bar-animated" style="width:100%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Friends section: headings are static, lists are updated by JS --}}
                        @if(!empty($highlightLabels))
                            <div class="small fw-semibold mb-1 mt-3">Top highlights (past {{ $pastDays }} days)</div>
                            <ol id="highlight-list" class="list-unstyled mb-0 small">
                                @foreach(range(1, 10) as $i)
                                    <li class="d-flex justify-content-between">
                                        <span class="placeholder-glow"><span class="placeholder" style="width:{{ 4 + ($i % 5) }}rem"></span></span>
                                        <span class="ms-2 placeholder-glow"><span class="placeholder" style="width:3rem"></span></span>
                                    </li>
                                @endforeach
                            </ol>
                            <div id="highlight-no-time-section" class="d-none">
                                <div class="small fw-semibold mb-1 mt-3 text-muted">No time logged</div>
                                <p id="highlight-no-time-list" class="mb-0 small text-muted"></p>
                            </div>
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
                        <div id="upload-stats">
                            {{-- Skeleton --}}
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold">Space used</span>
                                <span class="placeholder-glow"><span class="placeholder" style="width:5rem"></span></span>
                            </div>
                            <div class="progress mb-1" style="height:10px">
                                <div class="progress-bar bg-secondary progress-bar-striped progress-bar-animated" style="width:100%"></div>
                            </div>
                            <div class="small text-muted placeholder-glow"><span class="placeholder col-3"></span></div>
                            <div class="small fw-semibold mb-2 mt-3">Recent uploads</div>
                            <div class="d-flex justify-content-between gap-2">
                                @foreach(range(1, 4) as $_)
                                    <div style="flex:1;min-width:0;aspect-ratio:1;background:currentColor;opacity:0.1;border-radius:4px"></div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Preview modal (always in DOM so Bootstrap can bind) --}}
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
                </div>
            </div>
        </div>

    </div>
@endsection
