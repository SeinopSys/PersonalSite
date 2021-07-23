@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('global.inliner') }} <x-js-icon></x-js-icon></h2>
    <p>{!! __('inliner.about') !!}</p>
    <p>
        {{ __('inliner.import') }}
        <a id="import-html-file" href="#" class="ms-2">
            <x-fa icon="file-import" first></x-fa>
            {{ __('inliner.import-link') }}
        </a>
    </p>

    <form id="inliner-form">
        <div class="mb-3">
            <label for="inliner-styles">{{ __('inliner.styles') }}</label>
            <textarea class="form-control" id="inliner-styles" placeholder="a { text-decoration: none }" rows="8"
                      cols="30" required></textarea>
        </div>
        <div class="mb-3">
            <label for="inliner-markup">{{ __('inliner.markup') }}</label>
            <textarea class="form-control" id="inliner-markup" placeholder="<a href='http://example.com/'>Link</a>"
                      rows="18" required></textarea>
        </div>

        <button type="submit" class="btn btn-primary">
            <x-fa icon="file-download" first></x-fa>{{ __('inliner.dl-inlined') }}
        </button>
        <button type="reset" class="btn btn-danger">{{ __('global.clear') }}</button>
    </form>
@endsection
