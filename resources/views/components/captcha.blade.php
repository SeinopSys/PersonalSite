@if(config('hcaptcha.enabled') === true)
    <div class="h-captcha" data-size="invisible"></div>
    <div class="form-group hcaptcha">
        <label class="mb-1">{{ __('auth.field-antispam') }}</label>

        <small class="form-text mt-0">
            {{ __('global.hcaptcha_protecc.0') }}
            <a href="https://hcaptcha.com/privacy">{{ __('global.hcaptcha_protecc.1') }}</a>
            {{ __('global.hcaptcha_protecc.2') }}
            <a href="https://hcaptcha.com/terms">{{ __('global.hcaptcha_protecc.3') }}</a>
            {{ __('global.hcaptcha_protecc.4') }}
        </small>

        @if($errors->has('h-captcha-response'))
            <span class="invalid-feedback d-block">
                {{ $errors->first('h-captcha-response') }}
            </span>
        @endif
    </div>
@endif
