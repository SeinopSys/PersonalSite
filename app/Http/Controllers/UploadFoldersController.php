<?php

namespace App\Http\Controllers;

use App\Models\ImageUploadKey;
use App\Models\UploadFolder;
use App\Models\User;
use App\Util\Response;
use App\Util\UploadUtil;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UploadFoldersController extends Controller
{
    private function _folderJson(UploadFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
            'disable_thumbnails' => $folder->disable_thumbnails,
            'disable_conversion' => $folder->disable_conversion,
            'upload_count' => $folder->uploads()->count(),
            'upload_key' => $folder->uploadKey?->upload_key,
        ];
    }

    private function _findOwned(User $user, string $id): ?UploadFolder
    {
        return UploadFolder::where('id', $id)->where('user_id', $user->id)->first();
    }

    #[ApiResponse(status: 200, type: 'list<array{id: string, parent_id: string|null, name: string, disable_thumbnails: bool, disable_conversion: bool, upload_count: int, upload_key: string}>', description: 'Flat list of every folder belonging to the current user, including each folder\'s dedicated upload key. Build the nested tree client-side using parent_id.')]
    public function tree()
    {
        /** @var User $user */
        $user = Auth::user();

        $folders = $user->uploadFolders()->with('uploadKey')->withCount('uploads')->get();

        return Response::Done(['folders' => $folders->map(fn (UploadFolder $folder) => $this->_folderJson($folder))->values()]);
    }

    #[BodyParameter('name', 'Folder name, unique among its siblings.', required: true, type: 'string')]
    #[BodyParameter('parent_id', 'Parent folder UUID, or omitted/null to create a root-level folder.', required: false, type: 'string')]
    #[ApiResponse(status: 200, type: 'array{id: string, parent_id: string|null, name: string, disable_thumbnails: bool, disable_conversion: bool, upload_key: string}', description: 'The newly created folder and its dedicated upload key.')]
    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'bail|required|string|max:255',
            'parent_id' => 'bail|nullable|uuid|exists:upload_folders,id',
        ]);
        $parentId = $validated['parent_id'] ?? null;

        if ($parentId !== null && $this->_findOwned($user, $parentId) === null) {
            return Response::Fail(__('uploads.folder-not-found'));
        }

        $exists = UploadFolder::where('user_id', $user->id)
            ->where('parent_id', $parentId)
            ->where('name', $validated['name'])
            ->exists();
        if ($exists) {
            return Response::Fail(__('uploads.folder-name-taken'));
        }

        [$folder, $key] = DB::transaction(function () use ($user, $parentId, $validated) {
            $folder = new UploadFolder([
                'user_id' => $user->id,
                'parent_id' => $parentId,
                'name' => $validated['name'],
            ]);
            $folder->save();

            $key = new ImageUploadKey(['user_id' => $user->id, 'folder_id' => $folder->id]);
            $key->generateUploadKey();
            $key->save();

            return [$folder, $key];
        });

        return Response::Done(array_merge($this->_folderJson($folder), ['upload_key' => $key->upload_key]));
    }

    #[BodyParameter('name', 'New folder name, unique among its siblings.', required: false, type: 'string')]
    #[BodyParameter('disable_thumbnails', 'Skip preview thumbnail generation for files uploaded into this folder.', required: false, type: 'boolean')]
    #[BodyParameter('disable_conversion', 'Skip forced PNG re-encoding and JPEG-copy generation for files uploaded into this folder.', required: false, type: 'boolean')]
    #[ApiResponse(status: 200, type: 'array{id: string, parent_id: string|null, name: string, disable_thumbnails: bool, disable_conversion: bool}', description: 'The updated folder.')]
    public function update(Request $request, string $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $folder = $this->_findOwned($user, $id);
        if ($folder === null) {
            return Response::Fail(__('uploads.folder-not-found'));
        }

        $validated = $request->validate([
            'name' => 'bail|sometimes|required|string|max:255',
            'disable_thumbnails' => 'bail|sometimes|boolean',
            'disable_conversion' => 'bail|sometimes|boolean',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $folder->name) {
            $exists = UploadFolder::where('user_id', $user->id)
                ->where('parent_id', $folder->parent_id)
                ->where('name', $validated['name'])
                ->where('id', '!=', $folder->id)
                ->exists();
            if ($exists) {
                return Response::Fail(__('uploads.folder-name-taken'));
            }
            $folder->name = $validated['name'];
        }

        if (array_key_exists('disable_thumbnails', $validated)) {
            $folder->disable_thumbnails = $validated['disable_thumbnails'];
        }
        if (array_key_exists('disable_conversion', $validated)) {
            $folder->disable_conversion = $validated['disable_conversion'];
        }

        $folder->save();

        return Response::Done($this->_folderJson($folder));
    }

    #[ApiResponse(status: 200, description: 'The folder, all of its subfolders (recursively), and every file contained within them, have been permanently deleted.')]
    public function destroy(string $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $folder = $this->_findOwned($user, $id);
        if ($folder === null) {
            return Response::Fail(__('uploads.folder-not-found'));
        }

        $allUserFolders = $user->uploadFolders()->get();
        $subtreeIds = $folder->descendantIdsIncludingSelf($allUserFolders);

        $uploadsFolderPath = UploadUtil::getUploadDirectory();

        DB::transaction(function () use ($user, $subtreeIds, $uploadsFolderPath, $folder) {
            $uploads = $user->uploads()->whereIn('folder_id', $subtreeIds)->get();
            foreach ($uploads as $upload) {
                @unlink("$uploadsFolderPath/{$upload->filename}.{$upload->extension}");
                @unlink("$uploadsFolderPath/{$upload->filename}.jpg");
                @unlink("$uploadsFolderPath/{$upload->filename}p.{$upload->extension}");
                $upload->delete();
            }

            // Deleting the folder cascades its subfolders and their upload keys via FK constraints.
            $folder->delete();
        });

        return Response::Done();
    }

    #[ApiResponse(status: 200, type: 'array{upload_key: string}', description: "The folder's newly regenerated upload key.")]
    public function regenKey(string $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $folder = $this->_findOwned($user, $id);
        if ($folder === null) {
            return Response::Fail(__('uploads.folder-not-found'));
        }

        $key = $folder->uploadKey()->first();
        if ($key === null) {
            return Response::Fail(__('uploads.folder-not-found'));
        }

        $key->generateUploadKey();
        $key->save();

        return Response::Done(['upload_key' => $key->upload_key]);
    }
}
