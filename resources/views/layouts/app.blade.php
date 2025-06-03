<!DOCTYPE html>
<html lang="{{ $currLang = Lang::getLocale() }}">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta property="og:title" content="{{ $title = (isset($title) ? "$title - " : '') . config('app.name') }}">
  <meta
    property="og:description"
    content="{{ __(isset($title) && !empty($route = Request::route()) && ($routeName = $route->getName()) ? "$routeName.about" : 'about.briefdesc') }}"
  >
  <meta
    property="og:image"
    content="https://gravatar.com/avatar/f045e864fcd19a42e69f32581fe5020e?s=250&amp;r=g"
  >
  <meta name="description" content="{{ __('about.briefdesc') }}">
  <meta
    name="keywords"
    content="seinopsys,web,web development,webfejlesztés,web developer,webfejlesztő,php,css,linux,debian,hungary,magyar{{ isset($tags) ? ",$tags" : '' }}"
  >
  <meta
    name="viewport"
    content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, user-scalable=no"
  >
  <meta name="format-detection" content="telephone=no">
  <meta name="theme-color" content="#7aa7f0">

  <title>{{ $title }}</title>
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="manifest" href="/site.webmanifest">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/fontawesome.min.css" integrity="sha512-B46MVOJpI6RBsdcU307elYeStF2JKT87SsHZfRSkjVi4/iZ3912zXi45X5/CBr/GbCyLx6M1GQtTKYRd52Jxgw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/solid.min.css" integrity="sha512-/r+0SvLvMMSIf41xiuy19aNkXxI+3zb/BN8K9lnDDWI09VM0dwgTMzK7Qi5vv5macJ3VH4XZXr60ip7v13QnmQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="{{ mix('/css/bootstrap.css') }}">
  <link rel="stylesheet" href="{{ mix('/css/theme.css') }}">
  @if(!empty($css) && is_array($css))
    @foreach ($css as $line)
      <link rel="stylesheet" href="{{ mix("/css/$line.css") }}">
    @endforeach
  @endif
  @php
    /** @var string $currLang */
    $git_info = \App\Util\Core::GetFooterGitInfo();

    // Scripts
    echo "\n\t<script>window.Laravel = JSON.parse(".App\Util\JSON::Encode(App\Util\JSON::Encode([
        'csrfToken' => csrf_token(),
        'locale' => $currLang,
        'git' => $git_info,
        'ajaxErrors' => [
            404 => __('global.ajax-404'),
            500 => __('global.ajax-500'),
            503 => __('global.ajax-503'),
        ],
        'dialog' => [
            'close' => __('global.close'),
            'yes' => __('global.yes'),
            'no' => __('global.no'),
            'reload' => __('global.reload'),
            'submit' => __('global.submit'),
            'cancel' => __('global.cancel'),
            'defaultTitles' => [
                'fail' => __('global.dialog-title-fail'),
                'success' => __('global.dialog-title-success'),
                'wait' => __('global.dialog-title-wait'),
                'request' => __('global.dialog-title-request'),
                'confirm' => __('global.dialog-title-confirm'),
                'info' => __('global.dialog-title-info'),
            ],
            'defaultContent' => [
                'fail' => __('global.dialog-content-fail'),
                'success' => __('global.dialog-content-success'),
                'wait' => __('global.dialog-content-wait'),
                'request' => __('global.dialog-content-request'),
                'confirm' => __('global.dialog-content-confirm'),
                'info' => __('global.dialog-content-info'),
            ]
        ],
        'jsLocales' => new stdClass(),
    ])).")</script>\n";
  @endphp
</head>
<body>
<div id="wrap">
  @yield('content')
</div>

<footer>
    <span>{!! __('footer.built-with', [
        'laravel' => '<a href="https://laravel.com/">Laravel 12</a>',
        'bootstrap' => '<a href="https://getbootstrap.com/">Bootstrap 5</a>',
        'fa' => '<a href="https://fontawesome.com/license/free">Font Awesome Free</a>'
    ]) !!}</span>
  <span>&copy; {{ config('app.name') }}, 2016-{{ date('Y') }}</span>
  @if($git_info)
    <span>
        {{ __('footer.revision') }} {{--
        --}}<a
      href="{{ config('app.github_url') }}/commit/{{ $git_info['commit_id'] }}"
      title="{{ __('footer.commit') }}"
    >{{--
            --}}<code>{{ $git_info['commit_id'] }}</code>{{--
        --}}</a> {{--
        --}}{{ __('footer.created') }} {{--
        --}}{!! $git_info['commit_time'] !!}
    </span>
  @endif
</footer>

<!-- Scripts -->
<script
  src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"
  integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg=="
  crossorigin="anonymous"
></script>
<script
  src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.1.3/js/bootstrap.bundle.min.js"
  integrity="sha512-pax4MlgXjHEPfCwcJLQhigY7+N8rt6bVvWLFyUMuxShv170X53TRzGPmPkZmGBhk+jikR8WBM4yl7A9WMHHqvg=="
  crossorigin="anonymous"
  referrerpolicy="no-referrer"
></script>
@yield('js-locales')
<script src="{{ mix('/js/global.js') }}"></script>
@if(!empty($js) && is_array($js))
  @foreach ($js as $line)
    <script src="{{ mix("/js/$line.js") }}"></script>
    @if($line === 'friendlycaptcha')
      <script>window.Laravel.captchaKey = '{{ config('captcha.sitekey') }}';</script>
    @endif
  @endforeach
@endif
</body>
</html>
