@extends('layouts.container', [ 'bg' => 'danger', 'title' => '503' ])

@section('panel-heading')
{{ __('errors.503.title') }}
@endsection

@section('panel-body')
{{ __('errors.503.body') }}
@endsection
