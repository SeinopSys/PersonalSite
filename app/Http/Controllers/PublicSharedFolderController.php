<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PrivateSharePage;
use App\Models\SharedFolder;
use App\Util\Core;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a visitor with a shared folder's secret link can do: see the file list, download one file or all of them as a
 * zip. A link that cannot be used (expired, revoked, mistyped, or no such file) gets one and the same page, so nothing
 * can be learned from it about whether a link ever existed.
 */
class PublicSharedFolderController extends Controller
{
    use PrivateSharePage;

    private function resolve(Request $request, string $token): ?SharedFolder
    {
        // The person opening the link is usually not the site owner, so the owner's language settings don't apply
        $lang = in_array($request->query('lang'), ['hu', 'en'], true) ? $request->query('lang') : 'hu';
        App::setLocale($lang);

        $folder = preg_match('/^[A-Za-z0-9]{'.SharedFolder::TOKEN_LENGTH.'}$/', $token) === 1 ? SharedFolder::findByToken($token) : null;

        return $folder === null || $folder->isExpired() ? null : $folder;
    }

    private function gone(Request $request): Response
    {
        return $this->withSafeHeaders(fn (string $nonce) => response()->view('share-gone', ['lang' => App::getLocale(), 'nonce' => $nonce], 404));
    }

    public function show(Request $request, string $token)
    {
        $folder = $this->resolve($request, $token);
        if ($folder === null) {
            return $this->gone($request);
        }

        return $this->withSafeHeaders(fn (string $nonce) => response()->view('shared-folder-public', [
            'folder' => $folder,
            'files' => $folder->files()->get(),
            'lang' => App::getLocale(),
            'expiresAt' => $folder->expires_at?->toDateString(),
            'nonce' => $nonce,
        ]));
    }

    public function download(Request $request, string $token, string $fileId)
    {
        $folder = $this->resolve($request, $token);
        $file = $folder === null || !Str::isUuid($fileId) ? null : $folder->files()->where('id', $fileId)->first();
        $disk = Storage::disk('shared');
        if ($file === null || !$disk->exists($file->storage_name)) {
            return $this->gone($request);
        }

        // Always a download of opaque bytes, never rendered by the browser on this site's origin
        return $this->forDownload($disk->download($file->storage_name, $file->name, ['Content-Type' => 'application/octet-stream']));
    }

    public function zip(Request $request, string $token)
    {
        $folder = $this->resolve($request, $token);
        $files = $folder?->files()->get();
        if ($folder === null || $files->isEmpty()) {
            return $this->gone($request);
        }

        $disk = Storage::disk('shared');
        $path = tempnam(sys_get_temp_dir(), 'shared-zip');
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            abort(500);
        }
        $used = [];
        foreach ($files as $file) {
            if (!$disk->exists($file->storage_name)) {
                continue;
            }
            $entry = self::uniqueEntryName($file->name, $used);
            $zip->addFile($disk->path($file->storage_name), $entry);
            $zip->setCompressionName($entry, \ZipArchive::CM_STORE); // already-compressed formats are the usual case
        }
        $zip->close();

        $name = trim(preg_replace('/[^\p{L}\p{N} ._-]+/u', '_', $folder->name) ?? '', ' ._') ?: 'files';

        return $this->forDownload(response()->download($path, "$name.zip", ['Content-Type' => 'application/zip'])->deleteFileAfterSend());
    }

    /** Two files in a folder can share a name; the zip needs distinct entries. */
    private static function uniqueEntryName(string $name, array &$used): string
    {
        $candidate = $name;
        $n = 1;
        while (isset($used[$candidate])) {
            $dot = strrpos($name, '.');
            $candidate = $dot > 0 ? substr($name, 0, $dot)." (".++$n.")".substr($name, $dot) : $name.' ('.++$n.')';
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
