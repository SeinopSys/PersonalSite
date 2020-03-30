{{-- Do not load on LRC / About page --}}
@if(!in_array(Request::route()->getName(), ['lrc','about']))
    <script src="https://www.google.com/recaptcha/api.js?render={{ $key }}&onload=recaptchaReady" defer></script>
    <script>window.Laravel.recaptchaKey = '{{ $key }}'</script>
@endif
