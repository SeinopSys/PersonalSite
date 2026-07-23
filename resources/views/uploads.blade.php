<?php
/** @var $orderby string */
?>
@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('uploads.heading') }}<x-js-icon></x-js-icon></h2>

    <h3>{{ __('global.status') }}</h3>
    <p>{!! __('uploads.statustext',[
		'status' => '<span class="badge bg-'.($uploadingEnabled ? 'success' : 'danger').'">'.(strtoupper(__('global.'.($uploadingEnabled ? 'on' : 'off')))).'</span>'
	]) !!}</p>
    @if($uploadingEnabled)
        <div class="row mb-2">
            <div class="col-lg-2">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-danger" id="uploads-toggle"
                            data-dialogtitle="{{ __('uploads.action-dialog-heading',['action' => $disable = __('global.disable') ]) }}"
                            data-dialogcontent="<p>{{ __('uploads.action-dialog-text-disable') }}</p><p>{{ __('global.dialog-content-confirm') }}</p>">{{ $disable }}</button>
                </div>
            </div>
            <div class="col-lg-10">
                <div class="input-group">
                    <button class="btn btn-secondary" id="reveal-upload-key"
                            title="{{ __('uploads.keytoggle') }}">
                        <x-fa icon="eye"></x-fa>
                    </button>
                    <button class="btn btn-warning" id="regen-upload-key" title="{{ __('uploads.keyregen') }}"
                            data-dialogcontent="{{ __('uploads.action-dialog-content-regenkey') }}">
                        <x-fa icon="sync"></x-fa>
                    </button>
                    <input type="text" class="form-control" id="upload-key-display" readonly
                           value="{{ preg_replace('/./','*',$uploadKey) }}" data-key="{{ $uploadKey }}">
                    <span class="input-group-append">
                        <button class="btn btn-secondary" id="copy-upload-key">
                            <x-fa icon="copy"></x-fa>
                        </button>
                    </span>
                </div>
            </div>
        </div>
        <p class="mb-4">
            <a href="/docs/api#/operations/uploads.upload" target="_blank"><x-fa icon="book" first></x-fa>&nbsp;{{ __('uploads.apidocs-link') }}</a>
        </p>

        <h3>{{ __('uploads.folder-heading') }}</h3>
        <div class="row mb-4">
            <div class="col-lg-4">
                <div id="upload-folder-tree" data-selected-folder="{{ $folderId ?? '' }}"></div>
            </div>
            <div class="col-lg-8">
                <div id="upload-folder-toolbar" class="mb-3 d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-success" id="create-folder-btn">
                        <x-fa icon="folder-plus" first></x-fa> {{ __('uploads.folder-create') }}
                    </button>
                    <span class="folder-only d-none d-flex align-items-center gap-2 flex-wrap">
                        <strong id="upload-folder-name"></strong>
                        <button type="button" class="btn btn-sm btn-secondary" id="rename-folder-btn">
                            <x-fa icon="pen" first></x-fa> {{ __('uploads.folder-rename') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="delete-folder-btn">
                            <x-fa icon="trash" first></x-fa> {{ __('uploads.folder-delete') }}
                        </button>
                    </span>
                </div>
                <div id="upload-folder-panel"></div>
            </div>
        </div>

        <?php   /** @var $images \Illuminate\Pagination\LengthAwarePaginator */ ?>
        <h3>
            {{ __('uploads.listheading') }}
            <span class="badge bg-secondary" id="uploaded-total">{{ $images->total() }}</span>
        </h3>
        <p>
            <strong>{{ __('uploads.usedspace') }}:</strong> <span id="used-space">{{ $usedSpace }}</span>
        </p>
        <div class="mb-3 d-flex align-items-center gap-2 flex-wrap" id="ordering-links">
            <strong>{{ __('uploads.sorting') }}:</strong>
            @php $pageq = "page={$images->currentPage()}"; @endphp
            <div class="btn-group" role="group">
                @foreach (App\Http\Controllers\UploadsController::ORDERING as $k)
                    <a href="?{{ $pageq }}&orderby={{ $k }}+asc"
                       class="btn btn-sm btn-outline-secondary sort-link{{ $orderby === "$k asc" ? ' active' : '' }}">{{ __("uploads.sortasc-$k") }}</a>
                    <a href="?{{ $pageq }}&orderby={{ $k }}+desc"
                       class="btn btn-sm btn-outline-secondary sort-link{{ $orderby === "$k desc" ? ' active' : '' }}">{{ __("uploads.sortdesc-$k") }}</a>
                @endforeach
            </div>
        </div>
        @php
            $haveResults = $images->count() > 0;
            $havePreviousPages = $images->lastPage() > 0 && $images->currentPage() > $images->lastPage();
        @endphp
        @include('partials.uploads-imagelist')
        <div class="alert alert-info mb-0 {{ !($haveResults||$havePreviousPages) ? '' : 'd-none' }}" id="noimg-alert">
            <x-fa icon="info-circle" first></x-fa>
            {{ __('uploads.nouploads') }}
        </div>
    @else
        <?php   $canEnable = \App\Util\Permission::Sufficient('upload'); ?>
        @if($canEnable)
            <button type="button" class="btn btn-success" id="uploads-toggle"
                    data-dialogtitle="{{ __('uploads.action-dialog-heading',['action' => $enable = __('global.enable') ]) }}">{{ $enable }}</button>
        @else
            <div class="alert alert-info"><x-fa icon="info-circle" first></x-fa>{{ __('uploads.noperm') }}</div>
        @endif
    @endif
@endsection

@section('js-locales')
    {!! \App\Util\Core::ExportTranslations('uploads',[
        'multiwipe_dialog_title',
        'multiwipe_dialog_text',
        'keyregen',
        'action-dialog-content-regenkey',
        'folder-create-dialog-title',
        'folder-rename-dialog-title',
        'folder-name-label',
        'folder-delete-dialog-title',
        'folder-delete-dialog-text',
        'folder-key-heading',
        'folder-settings-thumbnails',
        'folder-settings-conversion',
        'folder-empty',
        'folder-root',
    ]) !!}
@endsection
