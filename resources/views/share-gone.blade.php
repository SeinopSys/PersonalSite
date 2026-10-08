<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('share.gone-title') }}</title>
    @include('share-head')
</head>
<body>
<main class="container py-5">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <h1 class="h3">{{ __('share.gone-title') }}</h1>
        @include('share-theme-toggle')
    </div>
    <p class="mt-3">{{ __('share.gone-body') }}</p>
    <p class="text-body-secondary">{{ __('share.gone-help') }}</p>
</main>
</body>
</html>
