<div id="upload-list">
    @php
    /** @var $image  \App\Models\Upload */
    @endphp
    @foreach ($images as $image)
    <div class="image-wrap embed-responsive embed-responsive-1by1">
        <div class="image embed-responsive-item" id="upload-{{ $image->id }}">
            <div class="info">
                <span class="orig_name"
                      title="{{ $image->orig_filename }}">{{ \App\Util\Core::TruncateFilename($image->orig_filename) }}</span>
                <span
                    class="sizes">{{ $image->width }}×{{ $image->height }} &bull; <abbr title="{!! \App\Util\Core::ReadableFilesize($image->original_size) !!} &plus; {!! \App\Util\Core::ReadableFilesize($image->additional_size) !!}">{!! \App\Util\Core::ReadableFilesize($image->size) !!}</abbr></span>
                <span
                    class="uploaded">{!! __('uploads.uploaded-at',['at' => \App\Util\Time::Tag($image->uploaded_at)]) !!}</span>
            </div>
            @php
                $fullUrl = "$image->host/{$image->filename}.{$image->extension}";
            @endphp
            @if($image->isVideo())
                <video src="{{ $fullUrl }}" muted loop playsinline controls preload="metadata"></video>
            @elseif($image->hasPreviewFile())
                <img src="{{ "$image->host/{$image->filename}p.png" }}" alt="{{ $image->orig_filename }}">
            @else
                <img src="{{ $fullUrl }}" alt="{{ $image->orig_filename }}">
            @endif
            <div class="actions">
                <a href="{{ "$image->host/{$image->filename}.{$image->extension}" }}"
                   title="Open full sized image in new window" target="_blank"><span
                        class="fa fa-external-link-alt text-primary"></span></a>
                <a href="#copy" class="copy-upload-link" title="Copy link to full sized image"><span
                        class="fa fa-copy text-primary"></span></a>
                <a href="#delete" class="wipe-upload" title="Delete image"
                   data-dialogtitle="{!! __('uploads.action-dialog-heading-imagedelete', ['id' => "<code>{$image->id}</code>" ]) !!}"
                   data-dialogcontent="{{ __('uploads.action-dialog-content-imagedelete') }}"><span
                        class="fa fa-trash  text-danger"></span></a>
                <label class="selection mb-0">
                    <input type="checkbox">
                </label>
            </div>
        </div>
    </div>
    @endforeach
</div>
@if(!$haveResults && $havePreviousPages)
    <div class="alert alert-info alert-out-of-bounds"><span
            class="fa fa-info-circle"></span> {{ __('global.page-out-of-bounds') }}</div>
@endif
<div class="pagination-wrapper" data-page="{{ $images->currentPage() }}">{{ $images->links() }}</div>
