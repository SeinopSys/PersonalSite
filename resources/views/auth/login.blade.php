@extends('layouts.container')

@section('panel-heading')
{{ __('auth.login') }}
@endsection

@section('panel-body')
    <div class="card-body">
        <form class="form-horizontal" role="form" method="POST" action="{{ url('/login') }}">
            @csrf

            <div class="mb-3 row">
                <label for="email" class="col-md-4 col-form-label text-left text-md-right fw-bold">{{ __('auth.field-email') }}</label>

                <div class="col-md-6">
                    <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email"
                           value="{{ old('email') }}" required autofocus>

                    @if ($errors->has('email'))
                        <span class="invalid-feedback">
                            <strong>{{ $errors->first('email') }}</strong>
                        </span>
                    @endif
                </div>
            </div>

            <div class="mb-3 row{{ $errors->has('password') ? ' has-error' : '' }}">
                <label for="password" class="col-md-4 col-form-label text-left text-md-right fw-bold">{{ __('auth.field-pass') }}</label>

                <div class="col-md-6">
                    <input id="password" type="password" class="form-control" name="password" required>

                    @if ($errors->has('password'))
                        <span class="invalid-feedback">
                            <strong>{{ $errors->first('password') }}</strong>
                        </span>
                    @endif
                </div>
            </div>

            <div class="mb-3 row">
                <div class="col-md-6 offset-md-4">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="remember"> {{ __('auth.check-remember') }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-3 row mb-0">
                <div class="col-md-8 offset-md-4">
                    <button type="submit" class="btn btn-primary">
                        {{ __('auth.btn-login') }}
                    </button>

                    {{--<a class="btn btn-link" href="{{ url('/password/reset') }}">
                        {{ __('auth.link-forgot') }}
                    </a>--}}
                </div>
            </div>
        </form>
    </div>
@endsection
