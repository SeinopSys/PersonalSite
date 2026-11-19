@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('shared.heading') }}</h2>
    <p>{{ __('shared.intro') }}</p>

    @include('partials.shared-status')

    <h3>{{ __('shared.your-folders') }}</h3>
    @if($folders->isEmpty())
        <p class="text-body-secondary">{{ __('shared.none') }}</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>{{ __('shared.name') }}</th><th>{{ __('shared.files') }}</th><th>{{ __('shared.expires') }}</th></tr></thead>
                <tbody>
                @foreach($folders as $folder)
                    <tr>
                        <td><a href="/shared-folders/{{ $folder->id }}">{{ $folder->name }}</a></td>
                        <td>{{ $folder->files_count }} ({{ \App\Util\Core::ReadableFilesize((int) $folder->files_sum_size) }})</td>
                        <td>
                            @if($folder->expires_at === null) {{ __('shared.never') }}
                            @elseif($folder->isExpired()) <span class="badge text-bg-danger">{{ __('shared.expired') }}</span> {{ $folder->expires_at->toDateString() }}
                            @else {{ $folder->expires_at->toDateString() }} @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <p class="text-body-secondary small">{{ __('shared.space', ['used' => $usedSpace, 'quota' => $quota]) }}</p>

    <h3 class="mt-4">{{ __('shared.create') }}</h3>
    <form method="post" action="/shared-folders" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-6">
            <label for="folder-name" class="form-label">{{ __('shared.name') }}</label>
            <input type="text" id="folder-name" name="name" class="form-control" maxlength="100" required value="{{ old('name') }}">
        </div>
        <div class="col-md-3">
            <label for="folder-expiry" class="form-label">{{ __('shared.expires') }}</label>
            <select id="folder-expiry" name="expires_in_days" class="form-select">
                <option value="">{{ __('shared.never') }}</option>
                @foreach($expiryDays as $days)
                    <option value="{{ $days }}">{{ __('shared.in-days', ['count' => $days]) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-grid"><button type="submit" class="btn btn-success">{{ __('shared.create') }}</button></div>
    </form>
@endsection
