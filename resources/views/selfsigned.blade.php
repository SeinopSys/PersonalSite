<?php

/** @var $ca_expires int */
$title = __('selfsigned.title'); ?>
@extends('layouts.container')

@section('panel-body')
    <h3>{{ $title }}<x-js-icon></x-js-icon></h3>
    <div class="oprah-wrapper">
        <img src="/img/oprah-CA-{{ App::getLocale() }}.png" alt="{{ __('selfsigned.description.oprah') }}" width="310" height="232">
        <p>{!! __('selfsigned.description.p1',[
            'openssl' => isset($openssl) ? __('selfsigned.description.using',['ver' => $openssl]) : '',
            'san' => '<code>subjectAltName</code>',
        ]) !!}<br>{{ __('selfsigned.description.p2.0') }}<br>{{ __('selfsigned.description.p2.1') }}: <a href="/selfsigned/rootCA">rootCA.pem</a>
            @if($ca_expires)
                ({{ __('selfsigned.description.p2.2') }} {!! \App\Util\Time::Tag($ca_expires) !!})
            @endif
        </p>
        <p class="text-info">
            <span class="fa fa-info fa-fw"></span>
            {!! __('selfsigned.description.before_2020_01_04', [
                'pem' => '<code>rootCA.pem</code>',
            ]) !!}
        </p>
        <p>{!! __('selfsigned.description.p3',['xca'=>'<a href="https://hohnstaedt.de/xca">XCA</a>']) !!}</p>
    </div>

    @if($errors->has('gen_err'))
        <div class="alert alert-danger">
            <x-fa icon="exclamation-triangle" first></x-fa>
            {{ __('selfsigned.err') }}: {{ $errors->first('gen_err') }}
        </div>
    @endif

    @if($openssl && $zip)
        <form method="POST" action="{{ route('selfsigned.make') }}">
            <div class="mb-3">
                <label for="common_name">{{ __('selfsigned.common_name') }}</label>
                <input type="text" id="common_name" name="common_name" class="form-control" placeholder="example.com"
                       pattern="^[\da-z.-]{3,253}$" maxlength="253" required autocomplete="off" spellcheck="false"
                       value="{{ request()->input('common_name') ?? old('common_name') }}">
                @if ($errors->has('common_name'))
                    <span class="invalid-feedback d-block">{{ $errors->first('common_name') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="subdomains">{{ __('selfsigned.subdomains') }} ({{ __('global.optional') }})</label>
                <p class="text-info my-2"><span
                        class="fa fa-info-circle"></span> {!! __('selfsigned.subdomains_explain',['short' => '<code>www</code>', 'long' => '<code>www.example.com</code>']) !!}
                </p>
                <textarea class="form-control" id="subdomains" name="subdomains" rows="8"
                          title="{{ __('selfsigned.subdomains_title') }}">{{ request()->input('subdomains') ?? old('subdomains') }}</textarea>
                @if ($errors->has('subdomains'))
                    <span class="invalid-feedback d-block">{{ $errors->first('subdomains') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="valid_for">{{ __('selfsigned.validity') }}</label>
                <input type="number" id="valid_for" name="valid_for" class="form-control" step="1" min="1" max="3652"
                       value="{{ request()->input('valid_for') ?? old('valid_for', '3652') }}" placeholder="3652">
            </div>

            <x-captcha :errors="$errors" />

            @csrf

            <button class="btn btn-primary"><x-fa icon="save" first></x-fa>{{ __('global.generate') }}
            </button>
        </form>
    @else
        @if(!$openssl)
            <div class="alert alert-danger"><x-fa icon="exclamation-triangle" first></x-fa>OpenSSL is not
                accessible. Please make sure it's installed and added to <code>PATH</code>.<br>
                <pre><code>{{ $opensslVersion }}</code></pre>
            </div>
        @endif
        @if(!$zip)
            <div class="alert alert-danger"><x-fa icon="exclamation-triangle" first></x-fa>The <code>zip</code>
                PHP extension is not installed, file generation would fail. Please contact the developer and let him
                know to fix it.
            </div>
        @endif
    @endif
@endsection
