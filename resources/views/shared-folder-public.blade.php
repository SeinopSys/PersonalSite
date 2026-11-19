@php
    $base = url('/s/'.$folder->token);
    $langQuery = request()->query('lang') ? '?lang='.$lang : '';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ $folder->name }}</title>
    @include('share-head')
</head>
<body>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <h1 class="h3 mb-1">{{ $folder->name }}</h1>
            <div class="text-body-secondary">
                {{ trans_choice('shared.public-count', $files->count(), ['count' => $files->count(), 'size' => \App\Util\Core::ReadableFilesize((int) $files->sum('size'))]) }}
                @if($expiresAt) · {{ __('shared.works-until', ['date' => $expiresAt]) }} @endif
            </div>
        </div>
        @include('share-theme-toggle')
    </div>

    @if($files->isEmpty())
        <p class="mt-4 text-body-secondary">{{ __('shared.public-empty') }}</p>
    @else
        <div class="table-responsive mt-3">
            <table class="table table-sm align-middle">
                <tbody>
                @foreach($files as $file)
                    <tr>
                        <td class="text-break"><a href="{{ $base.'/'.$file->id.$langQuery }}" download>{{ $file->name }}</a></td>
                        <td class="text-nowrap text-end text-body-secondary">{{ \App\Util\Core::ReadableFilesize($file->size) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($files->count() > 1)
            <a class="btn btn-primary" href="{{ $base.'/zip'.$langQuery }}">{{ __('shared.download-all') }}</a>
        @endif
    @endif
</main>
</body>
</html>
