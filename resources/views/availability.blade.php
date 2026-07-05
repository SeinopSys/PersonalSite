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

    <h2>{{ __('global.availability') }}</h2>

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
    <div class="d-flex flex-column gap-2 mb-3" style="max-width:420px">
        <div class="d-flex gap-2">
            <div class="flex-fill">
                <label for="avail-start" class="form-label mb-1">From</label>
                <input type="date" class="form-control" id="avail-start">
            </div>
            <div class="flex-fill">
                <label for="avail-end" class="form-label mb-1">To</label>
                <input type="date" class="form-control" id="avail-end">
            </div>
        </div>
        <div>
            <label for="avail-token" class="form-label mb-1">Token</label>
            <input type="text" class="form-control" id="avail-token" placeholder="highlight token">
        </div>
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-secondary" id="avail-fetch">Fetch</button>
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="debug-event-names">
                <label for="debug-event-names" class="form-check-label text-muted fst-italic">Show event names (debug)</label>
            </div>
        </div>
    </div>
    <div id="avail-calendar"
         class="border rounded overflow-hidden"
         data-username="{{ $user->name }}"></div>

    @if(!empty($isDeveloper))
    <h3 class="mt-5" id="highlights">Highlight tokens</h3>
    <p class="text-muted">
        Share a token with someone so they can see matching events in the availability API response under a
        <code>highlighted</code> key. Events matching any word in the group are surfaced; free/busy logic is unchanged.
        Query: <code>GET /api/availability/{{ $user->name }}?token=&lt;token&gt;</code>
    </p>

    @php
        $sortBase = '/availability?#highlights';
        $mkSortUrl = fn(string $s) => '/availability?sort='.$s.'&dir='.($sort === $s ? ($dir === 'asc' ? 'desc' : 'asc') : 'asc').'#highlights';
        $sortIcon  = fn(string $s) => $sort === $s ? '<span class="fa fa-chevron-'.($dir === 'asc' ? 'up' : 'down').' fa-xs ms-1"></span>' : '';
    @endphp
    <div class="d-flex align-items-center gap-2 mb-2 small text-muted">
        Sort:
        <a href="{{ $mkSortUrl('created_at') }}" class="link-secondary {{ $sort === 'created_at' ? 'fw-semibold text-body' : '' }}">Date{!! $sortIcon('created_at') !!}</a>
        <a href="{{ $mkSortUrl('label') }}" class="link-secondary {{ $sort === 'label' ? 'fw-semibold text-body' : '' }}">Name{!! $sortIcon('label') !!}</a>
    </div>

    <div class="accordion mb-3" id="highlights-accordion">
    @foreach($highlights as $ht)
    @php
        $collapseId = 'highlight-collapse-' . $ht->id;
        $hasError = $errors->getBag('words_'.$ht->id)->has('word');
        $isOpen = $hasError || session('open_highlight') === $ht->id;
    @endphp
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button p-2 {{ $isOpen ? '' : 'collapsed' }}" type="button"
                    data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                    aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
                @if($ht->archived)<span class="fa fa-archive me-2 text-muted" title="Archived"></span>@endif{{ $ht->label ?? '(unlabelled)' }}
                <span class="badge bg-secondary fw-normal ms-2">{{ $ht->words->count() }}</span>
                @if($ht->connections->isNotEmpty())
                    <span class="badge bg-info text-dark fw-normal ms-2" title="Linked to a connection">
                        <span class="fa fa-user me-1"></span>Connected
                    </span>
                @endif
                <span class="text-muted small fw-normal ms-2"><span class="fa fa-clock me-1"></span>{{ $ht->created_at->format('Y-m-d H:i') }}</span>
            </button>
        </h2>
        <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $isOpen ? 'show' : '' }}"
             data-bs-parent="#highlights-accordion">
            <div class="accordion-body">
                <div class="d-flex align-items-start gap-3 flex-wrap mb-3">
                    <div class="flex-grow-1">
                        <form method="POST" action="/dashboard/highlights/{{ $ht->id }}" class="d-flex gap-2 align-items-center">
                            @csrf
                            @method('PUT')
                            <input type="text" name="label" class="form-control form-control-sm" style="max-width:220px"
                                   placeholder="Label (optional)" value="{{ $ht->label }}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Rename</button>
                        </form>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="/dashboard/highlights/{{ $ht->id }}/archive">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $ht->archived ? 'btn-warning' : 'btn-outline-secondary' }}"
                                    title="{{ $ht->archived ? 'Unarchive: show in dashboard no-time list' : 'Archive: hide from dashboard no-time list' }}">
                                {{ $ht->archived ? 'Unarchive' : 'Archive' }}
                            </button>
                        </form>
                        <form method="POST" action="/dashboard/highlights/{{ $ht->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete this token and all its words?')">Delete token</button>
                        </form>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label mb-1 small fw-semibold">Connection</label>
                    @if($ht->connections->isEmpty())
                        <div>
                            <form method="POST" action="/dashboard/highlights/{{ $ht->id }}/create-connection">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Create connection for this token</button>
                            </form>
                        </div>
                    @else
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($ht->connections as $linkedConnection)
                                <a href="/connections?connection={{ $linkedConnection->id }}#connection-detail"
                                   class="btn btn-sm btn-outline-info">{{ $linkedConnection->name }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label mb-1 small fw-semibold">Token</label>
                    <div class="d-flex align-items-center gap-2">
                        <code class="text-break small">{{ $ht->token_base64 }}</code>
                        <button type="button" class="btn btn-sm btn-outline-secondary copy-token-btn"
                                data-token="{{ $ht->token_base64 }}">Copy</button>
                        <form method="POST" action="/dashboard/highlights/{{ $ht->id }}/regenerate">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning"
                                    onclick="return confirm('Regenerate this token? Anyone using the old token will lose access.')">Regenerate</button>
                        </form>
                    </div>
                </div>

                <div>
                    <label class="form-label mb-1 small fw-semibold">Words</label>
                    @if($ht->words->isEmpty())
                        <p class="text-muted small mb-1">No words yet.</p>
                    @else
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach($ht->words as $word)
                        <span class="badge bg-secondary d-flex align-items-center gap-1">
                            {{ $word->word }}
                            <form method="POST"
                                  action="/dashboard/highlights/{{ $ht->id }}/words/{{ $word->id }}"
                                  class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="btn-close btn-close-white"
                                        style="font-size:0.6rem"
                                        aria-label="Remove word"></button>
                            </form>
                        </span>
                        @endforeach
                    </div>
                    @endif

                    <form method="POST" action="/dashboard/highlights/{{ $ht->id }}/words">
                        @csrf
                        <div class="d-flex gap-2">
                            <input type="text" name="word"
                                   class="form-control form-control-sm @if($errors->getBag('words_'.$ht->id)->has('word')) is-invalid @endif"
                                   style="max-width:200px"
                                   placeholder="Add word…" required maxlength="255"
                                   value="{{ old('word') }}">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Add</button>
                        </div>
                        @if($errors->getBag('words_'.$ht->id)->has('word'))
                            <div class="text-danger small mt-1">{{ $errors->getBag('words_'.$ht->id)->first('word') }}</div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
    </div>

    <form method="POST" action="/dashboard/highlights" class="d-flex gap-2 align-items-center mt-2">
        @csrf
        <input type="text" name="label" class="form-control" style="max-width:240px"
               placeholder="New token label" maxlength="255" required>
        <button type="submit" class="btn btn-primary">Create token</button>
    </form>

    <div class="d-flex gap-2 align-items-center flex-wrap mt-3">
        <a href="{{ $highlights->isEmpty() ? '#' : '/dashboard/highlights/export' }}"
           class="btn btn-outline-secondary btn-sm {{ $highlights->isEmpty() ? 'disabled' : '' }}"
           @if($highlights->isEmpty()) aria-disabled="true" tabindex="-1" @endif>Export JSON</a>
        <form method="POST" action="/dashboard/highlights/import"
              enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
            @csrf
            <input type="file" name="file" class="form-control form-control-sm @error('file') is-invalid @enderror"
                   accept=".json" style="max-width:220px" required>
            <button type="submit" class="btn btn-sm btn-outline-primary">Import JSON</button>
        </form>
    </div>
    @error('file')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
    @endif
@endsection
