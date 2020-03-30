@extends('layouts.container', [ 'bg' => 'warning', 'title' => '404' ])

@section('panel-heading')
{{ __('errors.404.title') }}
@endsection

@section('panel-body')
{{ __('errors.404.body') }}
@endsection
