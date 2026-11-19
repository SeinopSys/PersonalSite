@php
    $base = url('/s/'.$folder->share_token);
    $langQuery = request()->query('lang') ? '?lang='.$lang : '';
    $all = $sections->flatten();
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
                {{ trans_choice('shared.public-count', $all->count(), ['count' => $all->count(), 'size' => \App\Util\Core::ReadableFilesize((int) $all->sum('size'))]) }}
                @if($expiresAt) · {{ __('shared.works-until', ['date' => $expiresAt]) }} @endif
            </div>
        </div>
        @include('share-theme-toggle')
    </div>

    @if($all->isEmpty())
        <p class="mt-4 text-body-secondary">{{ __('shared.public-empty') }}</p>
    @else
        @if($all->count() > 1)
            <p class="mt-3"><a class="btn btn-primary" href="{{ $base.'/zip'.$langQuery }}">{{ __('shared.download-all') }}</a></p>
        @endif
        @foreach($sections as $section => $uploads)
            @if($section !== '')<h2 class="h5 mt-4">{{ $section }}</h2>@endif
            <div class="row g-3 mt-0">
                @foreach($uploads as $upload)
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card h-100">
                            @if($upload->hasPreviewFile())
                                <a href="{{ $base.'/'.$upload->id.$langQuery }}" download><img class="card-img-top" src="{{ $base.'/'.$upload->id.'/thumb'.$langQuery }}" alt="" loading="lazy"></a>
                            @endif
                            <div class="card-body p-2 small">
                                <a href="{{ $base.'/'.$upload->id.$langQuery }}" download class="text-break d-block">{{ \App\Http\Controllers\PublicSharedFolderController::downloadName($upload) }}</a>
                                <span class="text-body-secondary">{{ \App\Util\Core::ReadableFilesize($upload->size - $upload->additional_size) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</main>
</body>
</html>
