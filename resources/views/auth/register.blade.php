@extends('layouts.container', [ 'title' => 'Register'])

@section('panel-heading')
Register
@endsection

@section('panel-body')
    <form class="form-horizontal frc-captcha" role="form" method="POST" action="{{ url('/register') }}">
        @csrf

        <div class="mb-3 row">
            <label for="name" class="col-md-4 col-form-label text-left text-md-right fw-bold">{{ __('auth.field-name') }}</label>

            <div class="col-md-6">
                <input id="name" type="text" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}" name="name"
                       value="{{ old('name') }}" required autofocus>

                @if ($errors->has('name'))
                    <span class="invalid-feedback">
                        <strong>{{ $errors->first('name') }}</strong>
                    </span>
                @endif
            </div>
        </div>

        <div class="mb-3 row">
            <label for="email" class="col-md-4 col-form-label text-left text-md-right fw-bold">{{ __('auth.field-email') }}</label>

            <div class="col-md-6">
                <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is invalid' : '' }}" name="email"
                       value="{{ old('email') }}" required>

                @if ($errors->has('email'))
                    <span class="invalid-feedback">
                        <strong>{{ $errors->first('email') }}</strong>
                    </span>
                @endif
            </div>
        </div>

        <div class="mb-3 row">
            <label for="password" class="col-md-4 col-form-label text-left text-md-right fw-bold">{{ __('auth.field-pass') }}</label>

            <div class="col-md-6">
                <input id="password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" required>

                @if ($errors->has('password'))
                    <span class="invalid-feedback">
                        <strong>{{ $errors->first('password') }}</strong>
                    </span>
                @endif
            </div>
        </div>

        <div class="mb-3 row{{ $errors->has('password_confirmation') ? ' has-error' : '' }}">
            <label for="password-confirm"
                   class="col-md-4 col-form-label text-left text-md-right fw-bold">{{ __('auth.field-passconf') }}</label>

            <div class="col-md-6">
                <input id="password-confirm" type="password" class="form-control"
                       name="password_confirmation" required>

                @if ($errors->has('password_confirmation'))
                    <span class="invalid-feedback">
                        <strong>{{ $errors->first('password_confirmation') }}</strong>
                    </span>
                @endif
            </div>
        </div>

        <x-captcha :errors="$errors"></x-captcha>

        <div class="mb-3 row">
            <div class="col-md-6 offset-md-4">
                <button type="submit" class="btn btn-primary">
                    {{ __('auth.btn-register') }}
                </button>
            </div>
        </div>
    </form>
@endsection
