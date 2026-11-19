<?php

namespace App\Http\Controllers;

use App\Models\SharedFile;
use App\Models\SharedFolder;
use App\Models\User;
use App\Util\Core;
use App\Util\UploadUtil;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in owner's side of shared folders: create them, put files in, copy the link, revoke it.
 * What a visitor with the link sees is in PublicSharedFolderController.
 */
class SharedFoldersController extends Controller
{
    private const EXPIRY_DAYS = [7, 30, 90, 365];

    private function user(): User
    {
        return Auth::user();
    }

    private function folderOf(string $id): SharedFolder
    {
        return $this->user()->sharedFolders()->where('id', $id)->firstOrFail();
    }

    /** Same cap as the image uploads, counted separately. */
    private function usedBytes(): int
    {
        return (int) SharedFile::whereIn('shared_folder_id', $this->user()->sharedFolders()->select('id'))->sum('size');
    }

    public function index()
    {
        return view('shared-folders', [
            'title' => __('shared.heading'),
            'folders' => $this->user()->sharedFolders()->withCount('files')->withSum('files', 'size')->orderBy('name')->get(),
            'expiryDays' => self::EXPIRY_DAYS,
            'usedSpace' => Core::ReadableFilesize($this->usedBytes()),
            'quota' => Core::ReadableFilesize((int) config('app.upload_quota_bytes')),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'expires_in_days' => ['nullable', 'integer', 'in:'.implode(',', self::EXPIRY_DAYS)],
        ]);

        $folder = SharedFolder::create([
            'user_id' => $this->user()->id,
            'name' => trim($validated['name']),
            'token' => SharedFolder::makeToken(),
            'expires_at' => isset($validated['expires_in_days']) ? now()->addDays((int) $validated['expires_in_days'])->startOfDay() : null,
        ]);

        return redirect("/shared-folders/{$folder->id}")->with('status', __('shared.created'));
    }

    public function show(string $id)
    {
        $folder = $this->folderOf($id);

        return view('shared-folder', [
            'title' => $folder->name,
            'js' => ['shared-folders'],
            'folder' => $folder,
            'files' => $folder->files()->get(),
            'expiryDays' => self::EXPIRY_DAYS,
            'maxFileBytes' => UploadUtil::maxFileBytes(),
            'maxFileSize' => Core::ReadableFilesize(UploadUtil::maxFileBytes()),
            'usedSpace' => Core::ReadableFilesize($this->usedBytes()),
            'quota' => Core::ReadableFilesize((int) config('app.upload_quota_bytes')),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $folder = $this->folderOf($id);
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'expiry' => ['nullable', 'regex:/^(keep|never|days:('.implode('|', self::EXPIRY_DAYS).'))$/'],
        ]);

        $folder->name = trim($validated['name']);
        // "keep" (the default) leaves the expiry alone, so renaming doesn't silently change who can still open the link
        $expiry = $validated['expiry'] ?? 'keep';
        if ($expiry === 'never') {
            $folder->expires_at = null;
        } elseif (str_starts_with($expiry, 'days:')) {
            $folder->expires_at = now()->addDays((int) substr($expiry, 5))->startOfDay();
        }
        $folder->save();

        return redirect("/shared-folders/{$folder->id}")->with('status', __('shared.saved'));
    }

    /** A new secret; the old link stops working immediately. */
    public function regen(string $id)
    {
        $folder = $this->folderOf($id);
        $folder->update(['token' => SharedFolder::makeToken()]);

        return redirect("/shared-folders/{$folder->id}")->with('status', __('shared.regenerated'));
    }

    public function destroy(string $id)
    {
        $this->folderOf($id)->delete();

        return redirect('/shared-folders')->with('status', __('shared.deleted'));
    }

    public function upload(Request $request, string $id)
    {
        $folder = $this->folderOf($id);
        $request->validate([
            'files' => 'required|array|min:1|max:50',
            'files.*' => 'required|file|max:'.intdiv(UploadUtil::maxFileBytes(), 1024),
        ]);

        /** @var list<UploadedFile> $files */
        $files = $request->file('files');
        $incoming = array_sum(array_map(fn (UploadedFile $f) => $f->getSize(), $files));
        if ($this->usedBytes() + $incoming > (int) config('app.upload_quota_bytes')) {
            throw ValidationException::withMessages(['files' => __('shared.over-quota')]);
        }

        $disk = Storage::disk('shared');
        foreach ($files as $file) {
            $storageName = Str::random(40);
            $disk->putFileAs('', $file, $storageName);
            SharedFile::create([
                'shared_folder_id' => $folder->id,
                'name' => self::cleanName($file->getClientOriginalName()),
                'storage_name' => $storageName,
                'size' => $file->getSize(),
            ]);
        }

        // The page sends one file per request (so each stays under the request size limit) and reads the result as JSON
        if ($request->expectsJson()) {
            return response()->json(['status' => true, 'count' => count($files)]);
        }

        return redirect("/shared-folders/{$folder->id}")->with('status', trans_choice('shared.uploaded', count($files), ['count' => count($files)]));
    }

    public function destroyFile(string $id, string $fileId)
    {
        $folder = $this->folderOf($id);
        $folder->files()->where('id', $fileId)->firstOrFail()->delete();

        return redirect("/shared-folders/{$folder->id}")->with('status', __('shared.file-deleted'));
    }

    /** What the sender's browser calls the file, made safe to show and to use as a download name. */
    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '');

        return $name === '' ? 'file' : mb_substr($name, 0, 255);
    }
}
