<!DOCTYPE html>
<html lang="{{ $currLang = Lang::getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="og:title" content="{{ $title = (isset($title) ? "$title - " : '') . config('app.name') }}">
    <meta property="og:description"
          content="{{ __(isset($title) && !empty($route = Request::route()) && ($routeName = $route->getName()) ? "$routeName.about" : 'about.briefdesc') }}">
    <meta property="og:image"
          content="https://gravatar.com/avatar/ebbac7b82d21d98c2638233797a323c2?s=250&r=g">
    <meta name="description" content="{{ __('about.briefdesc') }}">
    <meta name="keywords"
          content="seinopsys,web,web development,webfejlesztés,web developer,webfejlesztő,php,css,linux,debian,hungary,magyar{{ isset($tags) ? ",$tags" : '' }}">
    <meta name="viewport"
          content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#7aa7f0">

    <title>{{ $title }}</title>
    <link rel="shortcut icon" href="/favicon.ico">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/fontawesome.min.css"
          integrity="sha256-/sdxenK1NDowSNuphgwjv8wSosSNZB0t5koXqd7XqOI=" crossorigin="anonymous"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/solid.min.css"
          integrity="sha256-8DcgqUGhWHHsTLj1qcGr0OuPbKkN1RwDjIbZ6DKh/RA=" crossorigin="anonymous"/>
    @php
        /** @var string $currLang */
        echo \App\Util\Core::AssetURL('theme', 'css');
        if (!empty($css)){
            if (!is_array($css))
                throw new Exception('$css is not an array');
            foreach ($css as $line)
                echo "\t".\App\Util\Core::AssetURL($line, 'css');
        }

        $git_info = \App\Util\Core::GetFooterGitInfo();

        // Scripts
        echo "\n\t<script>window.Laravel = ".App\Util\JSON::Encode([
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
        ])."</script>\n";
    @endphp
</head>
<body>
<div id="wrap">
    @yield('content')
</div>

<footer>
    <span>{!! __('footer.built-with', [
        'laravel' => '<a href="https://laravel.com/">Laravel 6</a>',
        'bootstrap' => '<a href="https://getbootstrap.com/">Bootstrap 4</a>',
        'fa' => '<a href="https://fontawesome.com/license/free">Font Awesome Free 5.11.2</a>'
    ]) !!}</span>
    <span>&copy; {{ config('app.name') }}, 2016-{{ date('Y') }}</span>
    <span>
        {{ __('footer.revision') }} {{--
        --}}<a href="{{ config('app.github_url') }}/commit/{{ $git_info['commit_id'] }}" title="{{ __('footer.commit') }}">{{--
            --}}<code>{{ $git_info['commit_id'] }}</code>{{--
        --}}</a> {{--
        --}}{{ __('footer.created') }} {{--
        --}}{!! $git_info['commit_time'] !!}
    </span>
</footer>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.0/jquery.min.js"
        integrity="sha256-xNzN2a4ltkB44Mc/Jz3pT4iU1cmeR0FkXs4pru/JxaQ=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"
        integrity="sha256-x3YZWtRjM8bJqf48dFAv/qmgL68SI4jqNWeSLMZaMGA=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/js/bootstrap.min.js"
        integrity="sha256-WqU1JavFxSAMcLP2WIOI+GB2zWmShMI82mTpLDcqFUg=" crossorigin="anonymous"></script>
@yield('js-locales')
<?php
echo \App\Util\Core::AssetURL('moment', 'js');
if (!App::isLocale('en'))
    echo \App\Util\Core::AssetURL('moment-locale-'.App::getLocale(), 'js');
echo
\App\Util\Core::AssetURL('global', 'js'),
\App\Util\Core::AssetURL('dialog', 'js');
if (!empty($js)) {
    if (!is_array($js))
        throw new Exception('$js is not an array');
    foreach ($js as $line)
        echo \App\Util\Core::AssetURL($line, 'js');
} ?>
</body>
</html>
