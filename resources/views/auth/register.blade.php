@extends('layouts.app')

@section('panel-heading')
Register
@endsection

@section('panel-body')
    <form class="form-horizontal" role="form" method="POST" action="{{ url('/register') }}"
          data-recaptcha="true">
        @csrf

        <div class="form-group row{{ $errors->has('name') ? ' has-error' : '' }}">
            <label for="name" class="col-md-4 col-form-label text-left text-md-right font-weight-bold">{{ __('auth.field-name') }}</label>

            <div class="col-md-6">
                <input id="name" type="text" class="form-control" name="name"
                       value="{{ old('name') }}" required autofocus>

                @if ($errors->has('name'))
                    <span class="help-block">
                        <strong>{{ $errors->first('name') }}</strong>
                    </span>
                @endif
            </div>
        </div>

        <div class="form-group row{{ $errors->has('email') ? ' has-error' : '' }}">
            <label for="email" class="col-md-4 col-form-label text-left text-md-right font-weight-bold">{{ __('auth.field-email') }}</label>

            <div class="col-md-6">
                <input id="email" type="email" class="form-control" name="email"
                       value="{{ old('email') }}" required>

                @if ($errors->has('email'))
                    <span class="help-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                @endif
            </div>
        </div>

        <div class="form-group row{{ $errors->has('password') ? ' has-error' : '' }}">
            <label for="password" class="col-md-4 col-form-label text-left text-md-right font-weight-bold">{{ __('auth.field-pass') }}</label>

            <div class="col-md-6">
                <input id="password" type="password" class="form-control" name="password" required>

                @if ($errors->has('password'))
                    <span class="help-block">
                        <strong>{{ $errors->first('password') }}</strong>
                    </span>
                @endif
            </div>
        </div>

        <div class="form-group row{{ $errors->has('password_confirmation') ? ' has-error' : '' }}">
            <label for="password-confirm"
                   class="col-md-4 col-form-label text-left text-md-right font-weight-bold">{{ __('auth.field-passconf') }}</label>

            <div class="col-md-6">
                <input id="password-confirm" type="password" class="form-control"
                       name="password_confirmation" required>

                @if ($errors->has('password_confirmation'))
                    <span class="help-block">
                        <strong>{{ $errors->first('password_confirmation') }}</strong>
                    </span>
                @endif
            </div>
        </div>

        @if($errors->has('human'))
            <div class="form-group row recaptcha has-error">
                <label>{{ __('auth.field-antispam') }}</label>

                <div>
                    <span class="help-block">
                        <strong>{{ $errors->first('human') }}</strong>
                    </span>
                </div>
            </div>
        @endif

        <div class="form-group row">
            <div class="col-md-6 offset-md-4">
                <button type="submit" class="btn btn-primary">
                    {{ __('auth.btn-register') }}
                </button>
            </div>
        </div>
    </form>
@endsection
