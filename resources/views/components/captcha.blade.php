@if(config('captcha.enabled') === true)
    <div class="mb-3 captcha">
        <label class="mb-1">{{ __('auth.field-antispam') }}</label>

        <small class="form-text d-block mt-0">
            {{ __('global.captcha_protecc.0') }}
            <a href="https://friendlycaptcha.com/legal/privacy-end-users/">{{ __('global.captcha_protecc.1') }}</a>
            {{ __('global.captcha_protecc.2') }}
        </small>

        <div class="frc-captcha" data-sitekey="{{ config('captcha.sitekey') }}"></div>

        @if($errors->has('frc-captcha-response'))
            <span class="invalid-feedback d-block">
                {{ $errors->first('frc-captcha-response') }}
            </span>
        @endif
    </div>
@endif
