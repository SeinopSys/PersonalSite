@extends('layouts.container')

@section('panel-heading')
{{ __('auth.2fa-title') }}
@endsection

@section('panel-body')
    <div class="card-body">
        <p>{{ __('auth.2fa-description') }}</p>

        <form class="form-horizontal" role="form" method="POST" action="{{ url('/login/2fa') }}">
            @csrf

            <div class="mb-3 row{{ $errors->has('code') ? ' has-error' : '' }}">
                <label for="code" class="col-md-4 col-form-label text-left text-md-right fw-bold">{{ __('auth.2fa-field-code') }}</label>

                <div class="col-md-6">
                    <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                           class="form-control{{ $errors->has('code') ? ' is-invalid' : '' }}" name="code"
                           required autofocus>

                    @if ($errors->has('code'))
                        <span class="invalid-feedback">
                            <strong>{{ $errors->first('code') }}</strong>
                        </span>
                    @endif
                </div>
            </div>

            <div class="mb-3 row mb-0">
                <div class="col-md-8 offset-md-4">
                    <button type="submit" class="btn btn-primary">
                        {{ __('auth.2fa-btn-verify') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
