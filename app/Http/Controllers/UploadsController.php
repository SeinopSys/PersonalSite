<?php

namespace App\Http\Controllers;

use App\Models\ImageUploadKey;
use App\Models\Upload;
use App\Models\UploadFolder;
use App\Models\User;
use App\Util\Core;
use App\Util\UploadUtil;
use App\Util\Permission;
use App\Util\Response;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Laravel\Facades\Image;

class UploadsController extends Controller
{
    private function _getImageArray(User $user, ?string $folderId): LengthAwarePaginator
    {
        return $user->uploads()->where('folder_id', $folderId)->orderBy($this->_orderby_field, $this->_orderby_dir)->paginate(25)->setPath('/uploads');
    }

    /**
     * Resolves and validates the ?folder= query param against the current user's folders.
     * Returns null for the root folder (also the default when the param is absent).
     */
    private function _resolveFolderParam(Request $request, User $user): ?string
    {
        $folderId = $request->get('folder');
        if (empty($folderId)) {
            return null;
        }

        $folder = UploadFolder::where('id', $folderId)->where('user_id', $user->id)->first();
        if ($folder === null) {
            abort(404);
        }

        return $folder->id;
    }

    private function _usedSpaceInBytes(User $user): int
    {
        return $user->uploads()->sum('size');
    }

    private function _usedSpace(User $user): string
    {
        return Core::ReadableFilesize($this->_usedSpaceInBytes($user));
    }

    const ORDERING = ['uploaded_at', 'size'];

    private function _calcOrderBy(Request $request, &$out)
    {
        $out['orderby'] = $request->get('orderby');
        if (!isset($out['orderby']) && Session::has('orderby')) {
            $out['orderby'] = Session::get('orderby');
        }
        if (isset($out['orderby'])) {
            $possibleFields = implode('|', self::ORDERING);
            $out['orderby'] = str_replace('+', ' ', strtolower($out['orderby']));
            if (preg_match("~^($possibleFields) (asc|desc)$~i", $out['orderby'])) {
                Session::put('orderby', $out['orderby']);
                list($this->_orderby_field, $this->_orderby_dir) = explode(' ', $out['orderby']);
            }
        } else {
            $out['orderby'] = "{$this->_orderby_field} {$this->_orderby_dir}";
            Session::put('orderby', $out['orderby']);
        }
    }

    /** @var string */
    protected $_orderby_field = 'uploaded_at';
    protected $_orderby_dir = 'desc';

    public function index(Request $request)
    {
        /** @var $user User */
        $user = Auth::user();
        $upload = $user->rootImageUploadKey()->first();
        $uploadingEnabled = !empty($upload);
        $folderId = $uploadingEnabled ? $this->_resolveFolderParam($request, $user) : null;

        if ($request->expectsJson() && $uploadingEnabled) {
            $out = [];
            $this->_calcOrderBy($request, $out);
            $out['images'] = $this->_getImageArray($user, $folderId);
            $out['uploadingEnabled'] = true;
            $out['haveResults'] = $out['images']->count() > 0;
            $out['havePreviousPages'] = $out['images']->lastPage() > 0 && $out['images']->currentPage() > $out['images']->lastPage();
            return response()->json([
                'status' => true,
                'newhtml' => view('partials.uploads-imagelist', $out)->render(),
                'total' => $out['images']->total(),
                'usedSpace' => $this->_usedSpace($user),
            ]);
        }

        $out = [
            'js' => [],
            'css' => [],
        ];
        if (Permission::Sufficient('upload')) {
            $out['js'][] = 'uploads';
            $out['css'][] = 'uploads';
        }
        $out['images'] = [];
        $out['uploadingEnabled'] = $uploadingEnabled;

        if ($uploadingEnabled) {
            $out['uploadKey'] = $upload['upload_key'];
            $out['folderId'] = $folderId;

            $this->_calcOrderBy($request, $out);
            $out['images'] = $this->_getImageArray($user, $folderId);
        }

        $out['title'] = __('global.uploads');
        $out['usedSpace'] = $this->_usedSpace($user);

        return view('uploads', $out);
    }

    public function regen()
    {
        /** @var $user User */
        $user = Auth::user();
        /** @var $uploadAllowed \App\Models\ImageUploadKey */
        $uploadAllowed = $user->rootImageUploadKey()->first();
        if ($uploadAllowed === null) {
            return Response::Fail(__('uploads.statustext', ['status' => __('global.off')]));
        }

        $uploadAllowed->generateUploadKey();
        $uploadAllowed->save();

        return Response::Done(['upload_key' => $uploadAllowed->upload_key]);
    }

    public function setting($action)
    {
        /** @var $user User */
        $user = Auth::user();
        /** @var $uploadAllowed ImageUploadKey */
        $uploadAllowed = $user->rootImageUploadKey()->first();
        $uploadsFolderPath = UploadUtil::getUploadDirectory();
        switch ($action) {
            case 'enable':
                if (!empty($uploadAllowed->upload_key)) {
                    return Response::Success(__('uploads.ajax-already', ['status' => strtolower(__('global.on'))]));
                }

                if (!Permission::Sufficient('upload')) {
                    return Response::Fail(__('uploads.noperm'));
                }

                if (!@mkdir($uploadsFolderPath, 0777, true) && !is_dir($uploadsFolderPath)) {
                    return Response::Fail(__('uploads.folderfail'));
                }

                $newKey = new ImageUploadKey();
                $newKey->user_id = $user->id;
                $newKey->generateUploadKey();
                $newKey->save();

                return Response::Success(__('uploads.ajax-now', ['status' => strtolower(__('global.on'))]));
            case 'disable':
                if (empty($uploadAllowed->upload_key)) {
                    return Response::Success(__('uploads.ajax-already', ['status' => strtolower(__('global.off'))]));
                }

                // Disabling is account-wide: every folder, key, and file belonging to this user
                // is removed. Only THIS user's files are touched - the shared uploads directory
                // holds every user's files, so deletion must be scoped by ownership, not swept
                // wholesale (a prior version of this code incorrectly deleted the entire shared
                // directory here, destroying every user's uploads).
                DB::transaction(function () use ($user, $uploadsFolderPath) {
                    foreach ($user->uploads()->get() as $upload) {
                        @unlink("$uploadsFolderPath/{$upload->filename}.{$upload->extension}");
                        @unlink("$uploadsFolderPath/{$upload->filename}.jpg");
                        @unlink("$uploadsFolderPath/{$upload->filename}p.{$upload->extension}");
                    }
                    $user->uploads()->delete();
                    $user->uploadFolders()->delete();
                    $user->imageUploadKeys()->delete();
                });

                return Response::Success(__('uploads.ajax-now', ['status' => strtolower(__('global.off'))]));
            default:
                return Response::Fail(__('global.ajax-unknown-action', ['action' => $action]));
        }
    }

    /**
     * Processes and stores a single uploaded file, returning the same shape returned by the
     * single-file upload() response. Shared by both the single-file and multi-file (file[]) paths.
     */
    private function _processSingleUpload(
        UploadedFile $file,
        User $user,
        ?UploadFolder $folder,
        bool $secondaryDomain,
    ): array {
        $uploaddir = UploadUtil::getUploadDirectory();

        $disableThumbnails = $folder?->disable_thumbnails ?? false;
        $disableConversion = $folder?->disable_conversion ?? false;

        $upload = new Upload;
        $upload->generateRandomName();

        $extension = strtolower($file->getClientOriginalExtension());
        $not_animated = $extension !== 'gif';
        if ($not_animated && !$disableConversion) {
            $extension = 'png';
        }

        // Set metadata
        $image = Image::read($file);
        $upload->uploaded_by = $user->id;
        $upload->folder_id = $folder?->id;
        $upload->extension = $extension;
        $upload->orig_filename = $file->getClientOriginalName();
        $upload->mimetype = $file->getMimeType();
        $upload->width = $image->width();
        $upload->height = $image->height();
        $upload->secondary_domain = $secondaryDomain;
        $filenames = $upload->getFilenames();
        $file_paths = $upload->getFilePaths($uploaddir, $filenames);

        // Save original
        $file->move($uploaddir, $filenames['full']);

        // Create preview
        if (!$disableThumbnails) {
            UploadUtil::createPreviewImage($file_paths['full'], $upload->getPreviewDimensions(), $file_paths['preview']);
        }
        if (!$disableConversion) {
            UploadUtil::createJpegCopy($file_paths['full'], $file_paths['jpeg']);
        }

        // Re-encode original
        if ($not_animated && !$disableConversion) {
            UploadUtil::reencodeAsPng($file_paths['full']);
        }
        $upload->calculateFileSizes($file_paths);
        $upload->save();

        $hasJpegCopy = !$disableConversion;
        // Return jpeg URL in case size is > 500 KB and a jpeg copy actually exists
        $return_filename = ($hasJpegCopy && $upload->size > 512000)
            ? $filenames['jpeg']
            : $filenames['full'];
        $full = "{$upload->host}/$return_filename";

        return [
            'id' => $upload->id,
            'full' => $full,
            'full_png' => ($not_animated && !$disableConversion) ? "{$upload->host}/{$filenames['full']}" : null,
            'full_jpg' => $hasJpegCopy ? "{$upload->host}/{$filenames['jpeg']}" : null,
            'preview' => $disableThumbnails ? $full : "{$upload->host}/{$filenames['preview']}",
        ];
    }

    #[BodyParameter('upload_key', 'Secret upload key from your account settings, or from one of your folders. Uploading with a folder\'s key automatically places the file in that folder - no separate folder parameter is needed.', required: true, type: 'string')]
    #[BodyParameter('file', 'Image file to upload. GIFs are kept as-is; all other formats are re-encoded as PNG unless the target folder has conversion disabled. Mutually exclusive with file[].', required: false)]
    #[BodyParameter('file[]', 'Multiple image files to upload at once, into the same folder. Mutually exclusive with file. When used, the response shape changes to {"files": [...]} with one object per uploaded file, in the same order.', required: false, type: 'string[]', infer: false)]
    #[BodyParameter('domain', 'Optional secondary domain to serve the image(s) from.', required: false, type: 'string')]
    #[ApiResponse(status: 200, type: 'array{id: string, full: string, full_png: string|null, full_jpg: string|null, preview: string}|array{files: list<array{id: string, full: string, full_png: string|null, full_jpg: string|null, preview: string}>}', description: 'URLs for the uploaded image(s) and preview thumbnail(s). The first shape is returned for a single file uploaded via the file parameter; the second (files) shape is returned when files were uploaded via file[] instead. full_png is null for GIF uploads and for uploads into a folder with conversion disabled (full then keeps the original format/extension). full_jpg is null when the target folder has conversion disabled (no JPEG copy is generated). preview falls back to the same URL as full when the target folder has thumbnails disabled.')]
    #[ApiResponse(status: 400, type: 'array<string, list<string>>', description: 'Validation errors keyed by field name.')]
    #[ApiResponse(status: 401, description: 'Invalid or missing upload key.')]
    #[ApiResponse(status: 422, description: 'Uploading the file(s) would exceed the account\'s storage quota.')]
    public function upload(Request $request)
    {
        $isMultiple = is_array($request->file('file'));

        // Kept as a single unconditional array (rather than branching to build it) so Scramble's
        // static analysis can see the literal "image" rule on "file" and correctly document this
        // endpoint as a binary/multipart upload - the file[] case is validated separately below
        // without touching this variable, since Scramble can't trace conditional reassignment of it.
        $validation_rules = [
            'upload_key' => 'bail|required|string',
            'domain' => 'string',
            'file' => 'bail|required|image',
        ];

        if (!$isMultiple) {
            $validator = Validator::make($request->all(array_keys($validation_rules)), $validation_rules);
        } else {
            $validator = Validator::make(
                $request->all(['upload_key', 'domain', 'file']),
                [
                    'upload_key' => 'bail|required|string',
                    'domain' => 'string',
                    'file' => 'bail|required|array|min:1',
                    'file.*' => 'bail|required|image',
                ],
            );
        }
        if ($validator->fails()) {
            return response()->json($validator->messages(), 400);
        }

        $validated = $validator->valid();
        $secondary_domain = isset($validated['domain']) && $validated['domain'] === config('app.secondary_domain');

        $upload_key = $validated['upload_key'];
        /** @var $upload_allowed ImageUploadKey */
        $upload_allowed = ImageUploadKey::where('upload_key', $upload_key)->with('folder')->first();
        if (empty($upload_allowed)) {
            abort(401);
        }

        /** @var $user User */
        $user = $upload_allowed->user()->first();
        $folder = $upload_allowed->folder;

        $uploaddir = UploadUtil::getUploadDirectory();
        if (!@mkdir($uploaddir, 0777, true) && !is_dir($uploaddir)) {
            return response()->json(['message' => 'Could not create upload directory'], 500);
        }

        /** @var UploadedFile[] $files */
        $files = $isMultiple ? $validated['file'] : [$validated['file']];

        $requested_size = array_sum(array_map(fn (UploadedFile $file) => $file->getSize(), $files));
        $quota = config('app.upload_quota_bytes');
        if ($this->_usedSpaceInBytes($user) + $requested_size > $quota) {
            $readable = Core::ReadableFilesize($quota);
            return response()->json(['message' => "Uploading this would exceed the {$readable} maximum uploadable amount. Delete some images and try again."], 422);
        }

        $results = array_map(
            fn (UploadedFile $file) => $this->_processSingleUpload($file, $user, $folder, $secondary_domain),
            $files,
        );

        return $isMultiple ? ['files' => $results] : $results[0];
    }

    public function wipe(Request $request)
    {
        $this->validate($request, [
            'ids' => 'bail|required|array|min:1',
            'ids.*' => 'bail|required|uuid4',
        ]);
        $ids = $request->input('ids');

        $uploads = Upload::whereIn('id', $ids)->get();
        if ($uploads->isEmpty()) {
            return Response::Fail(__('uploads.ajax-wipe-notfound'));
        }

        /** @var $user User */
        $user = $uploads->first()->uploader()->first();
        $uploadsFolderPath = UploadUtil::getUploadDirectory();

        foreach ($uploads as $upload) {
            @unlink("$uploadsFolderPath/{$upload->filename}.{$upload->extension}");
            @unlink("$uploadsFolderPath/{$upload->filename}.jpg");
            @unlink("$uploadsFolderPath/{$upload->filename}p.{$upload->extension}");
            $upload->delete();
        }

        $folderId = $this->_resolveFolderParam($request, $user);
        $out = [];
        $this->_calcOrderBy($request, $out);
        $out['images'] = $this->_getImageArray($user, $folderId);
        $out['uploadingEnabled'] = true;
        $out['haveResults'] = $out['images']->count() > 0;
        $out['havePreviousPages'] = $out['images']->lastPage() > 0 && $out['images']->currentPage() > $out['images']->lastPage();
        return Response::Done([
            'newhtml' => view('partials.uploads-imagelist', $out)->render(),
            'total' => $out['images']->total(),
            'usedSpace' => $this->_usedSpace($user),
        ]);
    }
}
