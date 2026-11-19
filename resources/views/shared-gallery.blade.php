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
    <style>
        .gallery-item { display: block; position: relative; color: inherit; }
        .gallery-item:focus-visible { outline: 3px solid var(--bs-primary); outline-offset: -3px; }
        .gallery-placeholder { display: block; aspect-ratio: 1; }
        .gallery-badge { position: absolute; left: 6px; bottom: 6px; padding: 5px 7px; border-radius: 4px; line-height: 1; color: #fff; background: rgba(0, 0, 0, .65); pointer-events: none; }
        html.viewer-open { overflow: hidden; }

        .viewer { position: fixed; inset: 0; z-index: 1050; display: flex; flex-direction: column; height: 100vh; height: 100dvh; color: #fff; background: #0a0a0a; overscroll-behavior: contain; }
        .viewer[hidden] { display: none; }
        .viewer:focus { outline: none; }
        .viewer-bar { display: flex; align-items: center; gap: .5rem; padding: max(.5rem, env(safe-area-inset-top)) max(.75rem, env(safe-area-inset-right)) .5rem max(.75rem, env(safe-area-inset-left)); }
        .viewer-count { flex: none; opacity: .75; font-variant-numeric: tabular-nums; }
        .viewer-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .viewer-btn { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; padding: 0; border: 0; border-radius: 50%; color: #fff; background: transparent; cursor: pointer; }
        .viewer-btn:hover { background: rgba(255, 255, 255, .15); }
        .viewer-btn:focus-visible { outline: 2px solid #fff; outline-offset: 1px; }
        .viewer-btn:disabled { opacity: .25; cursor: default; background: transparent; }
        /* Single-finger gestures are ours (swipe to move on / down to close); pinching still zooms the page */
        .viewer-stage { position: relative; flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; touch-action: pinch-zoom; user-select: none; -webkit-user-select: none; }
        .viewer-stage img, .viewer-stage video { display: block; max-width: 100%; max-height: 100%; object-fit: contain; }
        .viewer-stage video { width: 100%; height: 100%; background: #000; }
        .viewer-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0, 0, 0, .35); }
        .viewer-nav.prev { left: max(.5rem, env(safe-area-inset-left)); }
        .viewer-nav.next { right: max(.5rem, env(safe-area-inset-right)); }
        .viewer-status { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1rem; text-align: center; pointer-events: none; }
        .viewer-spinner { width: 2.5rem; height: 2.5rem; border: 3px solid rgba(255, 255, 255, .25); border-top-color: #fff; border-radius: 50%; animation: viewer-spin .8s linear infinite; }
        @keyframes viewer-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .viewer-spinner { animation-duration: 2.4s; } }
    </style>
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
            {{-- The same options as the uploads page; plain links, so it works without scripts --}}
            <div class="d-flex align-items-center gap-2 flex-wrap mt-3">
                <strong>{{ __('uploads.sorting') }}:</strong>
                <div class="btn-group" role="group">
                    @foreach(\App\Http\Controllers\PublicSharedFolderController::ORDERINGS as $field)
                        @foreach(['asc', 'desc'] as $dir)
                            @php $query = array_filter(['lang' => request()->query('lang') ? $lang : null, 'orderby' => "$field $dir"]); @endphp
                            <a href="{{ $base.'?'.http_build_query($query) }}" class="btn btn-sm btn-outline-secondary{{ $ordering === [$field, $dir] ? ' active' : '' }}"
                               @if($ordering === [$field, $dir]) aria-current="true" @endif>{{ __("uploads.sort$dir-$field") }}</a>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif
        <div id="gallery"
             data-label-close="{{ __('shared.viewer-close') }}" data-label-prev="{{ __('shared.viewer-prev') }}"
             data-label-next="{{ __('shared.viewer-next') }}" data-label-download="{{ __('shared.viewer-download') }}"
             data-label-error="{{ __('shared.viewer-error') }}">
        @foreach($sections as $section => $uploads)
            @if($section !== '')<h2 class="h5 mt-4">{{ $section }}</h2>@endif
            <div class="row g-3 mt-0">
                @foreach($uploads as $upload)
                    @php
                        $name = \App\Http\Controllers\PublicSharedFolderController::downloadName($upload);
                        $url = $base.'/'.$upload->id;
                    @endphp
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card h-100">
                            {{-- Opens the viewer when scripts run; otherwise the file itself opens in the browser --}}
                            <a class="gallery-item" href="{{ $url.'/view'.$langQuery }}" data-kind="{{ $upload->isVideo() ? 'video' : 'image' }}"
                               data-src="{{ $url.'/view'.$langQuery }}" data-download="{{ $url.$langQuery }}" data-name="{{ $name }}"
                               aria-label="{{ __('shared.viewer-open', ['name' => $name]) }}">
                                @if($upload->hasPreviewFile())
                                    <img class="card-img-top" src="{{ $url.'/thumb'.$langQuery }}" alt="" loading="lazy">
                                @else
                                    <span class="gallery-placeholder bg-body-secondary"></span>
                                @endif
                                @if($upload->isVideo())
                                    <span class="gallery-badge" title="{{ __('shared.viewer-video') }}"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 5a2 2 0 0 1 2-2h7.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 4.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 13H2a2 2 0 0 1-2-2z"/></svg></span>
                                @endif
                            </a>
                            <div class="card-body p-2 small">
                                <a href="{{ $url.$langQuery }}" download class="text-break d-block">{{ $name }}</a>
                                <span class="text-body-secondary">{{ \App\Util\Core::ReadableFilesize($upload->size - $upload->additional_size) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
        </div>
    @endif
</main>
@php
    // The built, versioned script when there is one; the plain path otherwise (e.g. before the assets are built)
    try { $galleryJs = mix('/js/share-gallery.js'); } catch (\Throwable $e) { $galleryJs = '/js/share-gallery.js'; }
@endphp
<script nonce="{{ $nonce }}" src="{{ $galleryJs }}" defer></script>
</body>
</html>
