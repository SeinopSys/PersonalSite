<?php

use Illuminate\Support\Facades\Input;

$title = __('selfsigned.title'); ?>
@extends('layouts.container')

@section('panel-body')
    <h3>{{ $title }}{!! \App\Util\Core::JSIcon() !!}</h3>
    <p>{!! __('selfsigned.about.p1',[
		'openssl' => isset($openssl) ? __('selfsigned.about.using',['ver' => $openssl]) : '',
		'san' => '<code>subjectAltName</code>',
	]) !!}<br>{{ __('selfsigned.about.p2.0') }}<br>{{ __('selfsigned.about.p2.1') }}: <a href="/selfsigned/rootCA">rootCA.pem</a><br>{!! __('selfsigned.about.p3',['xca'=>'<a href="https://sourceforge.net/projects/xca/">XCA</a>']) !!}
    </p>

    @if(isset($generr))
        <div class="alert alert-danger"><span
                class="fa fa-exclamation-triangle"></span> {{ __('selfsigned.err') }}: {{ $generr }}</div>
    @endif

    @if($openssl && $zip)
        <form method="POST" data-recaptcha="true">
            <div class="form-group">
                <label for="commonName">{{ __('selfsigned.common_name') }}</label>
                @if ($errors->has('commonName'))
                    <p class="text-danger">{{ $errors->first('commonName') }}</p>
                @endif
                <input type="text" id="commonName" name="commonName" class="form-control" placeholder="example.com"
                       pattern="^[\da-z.-]{3,253}$" maxlength="253" required autocomplete="off" spellcheck="false"
                       value="{{ Request::input('commonName') ?? old('commonName') }}">
            </div>

            <div class="form-group">
                <label for="subdomains">{{ __('selfsigned.subdomains') }} ({{ __('global.optional') }})</label>
                @if ($errors->has('subdomains'))
                    <p class="text-danger">{{ $errors->first('subdomains') }}</p>
                @endif
                <p class="text-info"><span
                        class="fa fa-info-circle"></span> {!! __('selfsigned.subdomains_explain',['short' => '<code>www</code>', 'long' => '<code>www.example.com</code>']) !!}
                </p>
                <textarea class="form-control" id="subdomains" name="subdomains" rows="8"
                          title="{{ __('selfsigned.subdomains_title') }}">{{ Request::input('subdomains') ?? old('subdomains') }}</textarea>
            </div>

            <div class="form-group">
                <label for="validFor">{{ __('selfsigned.validity') }}</label>
                <input type="number" id="validFor" name="validFor" class="form-control" step="1" min="1" max="3652"
                       value="{{ Request::input('validFor') ?? old('validFor', '3652') }}" placeholder="3652">
            </div>

            @if($errors->has('human'))
                <div class="form-group recaptcha">
                    <label>{{ __('auth.field-antispam') }}</label>

                    <div>
	                <span class="invalid-feedback d-none">
	                    <strong>{{ $errors->first('human') }}</strong>
	                </span>
                    </div>
                </div>
            @endif

            @csrf

            <button class="btn btn-primary"><span class="fa fa-save"></span> {{ __('global.generate') }}
            </button>
        </form>
    @else
        @if(!$openssl)
            <div class="alert alert-danger"><span class="fa fa-exclamation-triangle"></span> OpenSSL is not
                accessible. Please make sure it's installed and added to <code>PATH</code>.<br>
                <pre><code>{{ $opensslVersion }}</code></pre>
            </div>
        @endif
        @if(!$zip)
            <div class="alert alert-danger"><span class="fa fa-exclamation-triangle"></span> The <code>zip</code>
                PHP extension is not installed, file generation would fail. Please contact the developer and let him
                know to fix it.
            </div>
        @endif
    @endif
@endsection
