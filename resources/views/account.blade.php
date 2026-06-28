@extends('layouts.container')

@section('panel-body')
    @php $user = Auth::user(); @endphp

    <h2>{{ __('global.account') }}</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <h3>{{ __('dashboard.profile-heading') }}</h3>

    <form method="POST" action="/account/profile" style="max-width:400px">
        @csrf

        <div class="mb-3">
            <label for="profile-name" class="form-label fw-semibold">{{ __('auth.field-name') }}</label>
            <input type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   id="profile-name"
                   name="name"
                   value="{{ old('name', $user->name) }}"
                   required>
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="mb-3">
            <label for="profile-email" class="form-label fw-semibold">{{ __('auth.field-email') }}</label>
            <input type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   id="profile-email"
                   name="email"
                   value="{{ old('email', $user->email) }}"
                   required>
            @error('email')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary mb-4">{{ __('dashboard.profile-btn-save') }}</button>
    </form>

    <h3>{{ __('dashboard.2fa-heading') }}</h3>
    @if($user->hasTwoFactorEnabled())
        <p>{!! __('dashboard.2fa-enabled-status') !!}</p>

        <form method="POST" action="/dashboard/2fa/disable" class="mb-4">
            @csrf

            <div class="mb-3" style="max-width:320px">
                <label for="2fa-disable-password" class="form-label fw-semibold">{{ __('dashboard.2fa-field-current-password') }}</label>
                <input type="password"
                       class="form-control @error('current_password') is-invalid @enderror"
                       id="2fa-disable-password"
                       name="current_password"
                       required>
                @error('current_password')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn-danger">{{ __('dashboard.2fa-btn-disable') }}</button>
        </form>
    @else
        <p>{!! __('dashboard.2fa-disabled-status') !!}</p>

        @if($twoFactorSetup ?? null)
            <p>{{ __('dashboard.2fa-setup-intro') }}</p>

            <div class="mb-3">
                {!! $twoFactorSetup['qr_code_svg'] !!}
            </div>

            <p>
                {{ __('dashboard.2fa-manual-key') }}
                <code>{{ $twoFactorSetup['secret'] }}</code>
            </p>

            <form method="POST" action="/dashboard/2fa/confirm" class="mb-4">
                @csrf

                <div class="mb-3" style="max-width:320px">
                    <label for="2fa-confirm-code" class="form-label fw-semibold">{{ __('dashboard.2fa-field-code') }}</label>
                    <input type="text" inputmode="numeric" autocomplete="one-time-code"
                           class="form-control @error('code') is-invalid @enderror"
                           id="2fa-confirm-code"
                           name="code"
                           required autofocus>
                    @error('code')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">{{ __('dashboard.2fa-btn-confirm') }}</button>
            </form>
        @else
            <form method="POST" action="/dashboard/2fa/setup" class="mb-4">
                @csrf
                <button type="submit" class="btn btn-primary">{{ __('dashboard.2fa-btn-setup') }}</button>
            </form>
        @endif
    @endif
@endsection
