@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('global.inliner') }} {!! \App\Util\Core::JSIcon() !!}</h2>
    <p>{!! __('inliner.about') !!}</p>
    <p>
        {{ __('inliner.import') }}
        <a id="import-html-file" href="#" class="ml-2">
            <span class="fa fa-file-import"></span>
            {{ __('inliner.import-link') }}
        </a>
    </p>

    <form id="inliner-form">
        <div class="form-group">
            <label for="inliner-styles">{{ __('inliner.styles') }}</label>
            <textarea class="form-control" id="inliner-styles" placeholder="a { text-decoration: none }" rows="8"
                      cols="30" required></textarea>
        </div>
        <div class="form-group">
            <label for="inliner-markup">{{ __('inliner.markup') }}</label>
            <textarea class="form-control" id="inliner-markup" placeholder="<a href='http://example.com/'>Link</a>"
                      rows="18" required></textarea>
        </div>

        <button type="submit" class="btn btn-primary"><span
                class="fa fa-file-download"></span> {{ __('inliner.dl-inlined') }}</button>
        <button type="reset" class="btn btn-danger">{{ __('global.clear') }}</button>
    </form>
@endsection
