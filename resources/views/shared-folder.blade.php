@extends('layouts.container')

@section('panel-body')
    <p class="mb-1"><a href="/shared-folders">&larr; {{ __('shared.heading') }}</a></p>
    <h2>{{ $folder->name }}</h2>

    @include('partials.shared-status')

    <h3 class="h5">{{ __('shared.link') }}</h3>
    <div class="input-group mb-1">
        <input type="text" id="share-url" class="form-control" readonly data-share-url value="{{ $folder->url() }}">
        <button type="button" class="btn btn-secondary" data-copy-target="#share-url" title="{{ __('shared.copy') }}"><x-fa icon="copy"></x-fa></button>
    </div>
    <p class="small text-body-secondary">
        {{ __('shared.link-help') }}
        @if($folder->isExpired()) <strong class="text-danger">{{ __('shared.expired-on', ['date' => $folder->expires_at->toDateString()]) }}</strong>
        @elseif($folder->expires_at) {{ __('shared.works-until', ['date' => $folder->expires_at->toDateString()]) }}
        @else {{ __('shared.no-expiry') }} @endif
    </p>
    <form method="post" action="/shared-folders/{{ $folder->id }}/regen" class="mb-4" data-confirm="{{ __('shared.regen-confirm') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-warning"><x-fa icon="sync" first></x-fa> {{ __('shared.regen') }}</button>
    </form>

    <h3 class="h5">{{ __('shared.files') }}</h3>
    @if($files->isEmpty())
        <p class="text-body-secondary">{{ __('shared.no-files') }}</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <tbody>
                @foreach($files as $file)
                    <tr>
                        <td class="text-break">{{ $file->name }}</td>
                        <td class="text-nowrap text-end">{{ \App\Util\Core::ReadableFilesize($file->size) }}</td>
                        <td class="text-end">
                            <form method="post" action="/shared-folders/{{ $folder->id }}/files/{{ $file->id }}" data-confirm="{{ __('shared.file-delete-confirm', ['name' => $file->name]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('shared.delete') }}"><x-fa icon="trash"></x-fa></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <form method="post" action="/shared-folders/{{ $folder->id }}/files" enctype="multipart/form-data" class="mb-1">
        @csrf
        <div class="input-group">
            <input type="file" name="files[]" class="form-control" multiple required>
            <button type="submit" class="btn btn-primary"><x-fa icon="upload" first></x-fa> {{ __('shared.upload') }}</button>
        </div>
    </form>
    <p class="small text-body-secondary">{{ __('shared.limits', ['max' => $maxFileSize, 'used' => $usedSpace, 'quota' => $quota]) }}</p>

    <h3 class="h5 mt-4">{{ __('shared.settings') }}</h3>
    <form method="post" action="/shared-folders/{{ $folder->id }}" class="row g-2 align-items-end mb-4">
        @csrf @method('PUT')
        <div class="col-md-5">
            <label for="folder-name" class="form-label">{{ __('shared.name') }}</label>
            <input type="text" id="folder-name" name="name" class="form-control" maxlength="100" required value="{{ old('name', $folder->name) }}">
        </div>
        <div class="col-md-4">
            <label for="folder-expiry" class="form-label">{{ __('shared.expires') }}</label>
            <select id="folder-expiry" name="expiry" class="form-select">
                <option value="keep">{{ __('shared.keep') }}</option>
                <option value="never">{{ __('shared.never') }}</option>
                @foreach($expiryDays as $days)
                    <option value="days:{{ $days }}">{{ __('shared.in-days', ['count' => $days]) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-grid"><button type="submit" class="btn btn-secondary">{{ __('shared.save') }}</button></div>
    </form>

    <form method="post" action="/shared-folders/{{ $folder->id }}" data-confirm="{{ __('shared.delete-confirm', ['name' => $folder->name]) }}">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger"><x-fa icon="trash" first></x-fa> {{ __('shared.delete-folder') }}</button>
    </form>
@endsection
