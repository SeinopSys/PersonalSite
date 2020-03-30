@extends('layouts.container')

@section('panel-body')
    @php
        $user = Auth::user();
    @endphp
    <h2>{{ __('global.dashboard') }}</h2>

    <h3>{{ __('dashboard.greeting',['name' => $user->name]) }}</h3>
    <ul>
        <li><strong>{{ __('global.role') }}:</strong> {{\App\Util\Permission::LocalizedRoleName($user->role)}}</li>
        <li><strong>UUID:</strong> {{ $user->id }}</li>
        <li><strong>Stored language:</strong> {{ strtoupper($user->lang) }}</li>
    </ul>
@endsection
