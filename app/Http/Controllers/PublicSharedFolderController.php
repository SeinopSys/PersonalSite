<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PrivateSharePage;
use App\Models\Upload;
use App\Models\UploadFolder;
use App\Util\UploadUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a visitor with a shared uploads folder's secret link can do: see the files in the folder and its subfolders,
 * download one or all of them as a zip. A link that cannot be used (expired, stopped, replaced, mistyped, or no such
 * file) gets one and the same page, so nothing can be learned from it about whether a link ever existed.
 * Files are always handed out through here (never as the image host's public URL) so the page works on its own.
 */
class PublicSharedFolderController extends Controller
{
    use PrivateSharePage;

    /** The same options as the uploads page (UploadsController::ORDERING), newest first by default. */
    public const ORDERINGS = ['uploaded_at', 'size'];

    private const VIEW_TYPES = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'bmp' => 'image/bmp',
        'webp' => 'image/webp', 'webm' => 'video/webm', 'mp4' => 'video/mp4',
    ];

    /** [field, direction] from ?orderby=uploaded_at+desc, the default when it is missing or not one of the options. */
    public static function ordering(Request $request): array
    {
        $given = str_replace('+', ' ', strtolower((string) $request->query('orderby')));
        if (preg_match('/^('.implode('|', self::ORDERINGS).') (asc|desc)$/', $given, $m)) {
            return [$m[1], $m[2]];
        }

        return ['uploaded_at', 'desc'];
    }

    /**
     * The shared folder and, per folder in its subtree, the uploads in it. Sections are keyed by the folder's path
     * below the shared one ('' for the shared folder itself) and sorted by it.
     *
     * @return array{0: UploadFolder, 1: Collection<string, Collection<int, Upload>>}|null
     */
    private function resolve(Request $request, string $token): ?array
    {
        // The person opening the link is usually not the site owner, so the owner's language settings don't apply
        $lang = in_array($request->query('lang'), ['hu', 'en'], true) ? $request->query('lang') : 'hu';
        App::setLocale($lang);

        $folder = preg_match('/^[A-Za-z0-9]{'.UploadFolder::SHARE_TOKEN_LENGTH.'}$/', $token) === 1 ? UploadFolder::findByShareToken($token) : null;
        if ($folder === null || $folder->isShareExpired()) {
            return null;
        }

        $all = UploadFolder::where('user_id', $folder->user_id)->get();
        $ids = $folder->descendantIdsIncludingSelf($all);
        $byId = $all->keyBy('id');
        $pathOf = function (UploadFolder $f) use ($folder, $byId): string {
            $names = [];
            for ($current = $f; $current !== null && $current->id !== $folder->id; $current = $byId->get($current->parent_id)) {
                array_unshift($names, $current->name);
            }

            return implode(' / ', $names);
        };

        // Size is what the page shows for a file: the file itself, without the thumbnail and the JPEG copy
        [$field, $dir] = self::ordering($request);
        $query = Upload::whereIn('folder_id', $ids);
        $field === 'size' ? $query->orderByRaw("(size - additional_size) $dir") : $query->orderBy($field, $dir);
        $sections = $query->orderBy('filename')->get()
            ->groupBy(fn (Upload $u) => $pathOf($byId->get($u->folder_id)))
            ->sortKeys();

        return [$folder, $sections];
    }

    private function gone(): Response
    {
        return $this->withSafeHeaders(fn (string $nonce) => response()->view('share-gone', ['lang' => App::getLocale(), 'nonce' => $nonce], 404));
    }

    public function show(Request $request, string $token)
    {
        $resolved = $this->resolve($request, $token);
        if ($resolved === null) {
            return $this->gone();
        }
        [$folder, $sections] = $resolved;

        return $this->withSafeHeaders(fn (string $nonce) => response()->view('shared-gallery', [
            'folder' => $folder,
            'sections' => $sections,
            'lang' => App::getLocale(),
            'ordering' => self::ordering($request),
            'expiresAt' => $folder->share_expires_at?->toDateString(),
            'nonce' => $nonce,
        ]));
    }

    /** The original file, always as a download so the browser never renders it on this site's origin. */
    public function download(Request $request, string $token, string $uploadId)
    {
        $upload = $this->findUpload($request, $token, $uploadId);
        $path = $upload === null ? null : UploadUtil::getUploadDirectory().'/'.$upload->getFilenames()['full'];
        if ($path === null || !is_file($path)) {
            return $this->gone();
        }

        return $this->forDownload(response()->download($path, self::downloadName($upload), ['Content-Type' => 'application/octet-stream']));
    }

    /** What the gallery viewer shows: the original file inline, with Range support so videos can be played and seeked. */
    public function view(Request $request, string $token, string $uploadId)
    {
        $upload = $this->findUpload($request, $token, $uploadId);
        // Only the types an upload can be, each with its own fixed type: whatever the file claims is never trusted
        $type = $upload === null ? null : self::VIEW_TYPES[$upload->extension] ?? null;
        $path = $upload === null ? null : UploadUtil::getUploadDirectory().'/'.$upload->getFilenames()['full'];
        if ($type === null || !is_file($path)) {
            return $this->gone();
        }

        $response = $this->forDownload(response()->file($path, ['Content-Type' => $type, 'Content-Disposition' => 'inline']));
        // Navigating back and forth in the viewer shouldn't fetch the same file again, but a link taken away stops soon
        $response->headers->set('Cache-Control', 'private, max-age=300');

        return $response;
    }

    /** The generated thumbnail, a PNG made by this site. */
    public function thumbnail(Request $request, string $token, string $uploadId)
    {
        $upload = $this->findUpload($request, $token, $uploadId);
        $path = $upload === null ? null : UploadUtil::getUploadDirectory().'/'.$upload->getFilenames()['preview'];
        if ($path === null || !is_file($path)) {
            return $this->gone();
        }

        return $this->forDownload(response()->file($path, ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline']));
    }

    public function zip(Request $request, string $token)
    {
        $resolved = $this->resolve($request, $token);
        if ($resolved === null || $resolved[1]->flatten()->isEmpty()) {
            return $this->gone();
        }
        [$folder, $sections] = $resolved;

        $dir = UploadUtil::getUploadDirectory();
        $path = tempnam(sys_get_temp_dir(), 'shared-zip');
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            abort(500);
        }
        $used = [];
        foreach ($sections as $section => $uploads) {
            foreach ($uploads as $upload) {
                $file = "$dir/".$upload->getFilenames()['full'];
                if (!is_file($file)) {
                    continue;
                }
                $entry = self::uniqueEntryName(($section === '' ? '' : str_replace(' / ', '/', $section).'/').self::downloadName($upload), $used);
                $zip->addFile($file, $entry);
                $zip->setCompressionName($entry, \ZipArchive::CM_STORE); // images and video are compressed already
            }
        }
        $zip->close();

        $name = trim(preg_replace('/[^\p{L}\p{N} ._-]+/u', '_', $folder->name) ?? '', ' ._') ?: 'files';

        return $this->forDownload(response()->download($path, "$name.zip", ['Content-Type' => 'application/zip'])->deleteFileAfterSend());
    }

    private function findUpload(Request $request, string $token, string $uploadId): ?Upload
    {
        $resolved = $this->resolve($request, $token);
        if ($resolved === null || !Str::isUuid($uploadId)) {
            return null;
        }

        return $resolved[1]->flatten()->firstWhere('id', $uploadId);
    }

    /** The name it was uploaded under, with the extension it has now (uploads are re-encoded as PNG, for one). */
    public static function downloadName(Upload $upload): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $upload->orig_filename)), PATHINFO_FILENAME);
        $base = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $base) ?? '');

        return ($base === '' ? 'file' : mb_substr($base, 0, 200)).'.'.$upload->extension;
    }

    /** Two files can share a name; the zip needs distinct entries. */
    private static function uniqueEntryName(string $name, array &$used): string
    {
        $candidate = $name;
        $n = 1;
        while (isset($used[$candidate])) {
            $dot = strrpos($name, '.');
            $candidate = $dot > 0 ? substr($name, 0, $dot).' ('.++$n.')'.substr($name, $dot) : $name.' ('.++$n.')';
        }
        $used[$candidate] = true;

        return $candidate;
    }

    private function forDownload(Response $response): Response
    {
        $response->headers->add([
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);

        return $response;
    }
}
