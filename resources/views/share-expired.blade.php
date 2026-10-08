@php
    $date = fn (?string $d) => $d ? \Carbon\Carbon::parse($d)->format('Y. m. d.') : '—';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('share.expired-title') }}</title>
    @include('share-style')
</head>
<body>
<main>
    <h1>{{ __('share.expired-title') }}</h1>
    <p>{{ __('share.expired-body', ['date' => $date($expiredOn)]) }}</p>
    <p class="muted">{{ __('share.expired-help') }}</p>
</main>
</body>
</html>
