<?php

namespace App\Http\Controllers;

use App\Models\ImageUpload;
use App\Models\Upload;
use App\Models\User;
use App\Util\Core;
use App\Util\Permission;
use App\Util\Response;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

class UploadsController extends Controller
{
    private function _getImageArray(User $user): LengthAwarePaginator
    {
        return $user->uploads()->orderBy($this->_orderby_field, $this->_orderby_dir)->paginate(25)->setPath('/uploads');
    }

    private function _usedSpaceInBytes(User $user): int
    {
        return $user->uploads()->sum('size');
    }

    private function _usedSpace(User $user): string
    {
        return Core::ReadableFilesize($user->uploads()->sum('size'));
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

    public function getRelativeUploadDirectory(bool $startingSlash = true): string
    {
        return ($startingSlash ? '/' : '').'uploads';
    }

    public function getUploadDirectory(): string
    {
        return storage_path("app/public/{$this->getRelativeUploadDirectory(false)}");
    }

    /** @var string */
    protected $_orderby_field = 'uploaded_at';
    protected $_orderby_dir = 'desc';

    public function index(Request $request)
    {
        $out = [
            'js' => [],
            'css' => [],
        ];
        if (Permission::Sufficient('upload')) {
            $out['js'][] = 'uploads';
            $out['css'][] = 'uploads';
        }
        $out['images'] = [];
        /** @var $user User */
        $user = Auth::user();
        $upload = $user->imageUpload()->first();
        $out['uploadingEnabled'] = !empty($upload);

        if ($out['uploadingEnabled']) {
            $out['uploadKey'] = $upload['upload_key'];

            $this->_calcOrderBy($request, $out);
            $out['images'] = $this->_getImageArray($user);
        }

        $out['title'] = __('global.uploads');
        $out['usedSpace'] = $this->_usedSpace($user);

        return view('uploads', $out);
    }

    public function regen()
    {
        /** @var $user User */
        $user = Auth::user();
        /** @var $uploadAllowed \App\Models\ImageUpload */
        $uploadAllowed = $user->imageUpload()->first();
        if ($uploadAllowed === null) {
            Response::Fail(__('uploads.statustext', ['status' => __('global.off')]));
        }

        $uploadAllowed->generateUploadKey();
        $uploadAllowed->save();

        Response::Done(['upload_key' => $uploadAllowed->upload_key]);
    }

    public function setting($action)
    {
        /** @var $user User */
        $user = Auth::user();
        /** @var $uploadAllowed ImageUpload */
        $uploadAllowed = $user->imageUpload()->first();
        $uploadsFolderPath = $this->getUploadDirectory();
        switch ($action) {
            case 'enable':
                if (!empty($uploadAllowed->upload_key)) {
                    Response::Success(__('uploads.ajax-already', ['status' => strtolower(__('global.on'))]));
                }

                if (!Permission::Sufficient('upload')) {
                    Response::Fail(__('uploads.noperm'));
                }

                if (!@mkdir($uploadsFolderPath, 0777, true) && !is_dir($uploadsFolderPath)) {
                    Response::Fail(__('uploads.folderfail'));
                }

                $ImageUpload = new ImageUpload();
                $ImageUpload->user_id = $user->id;
                $ImageUpload->generateUploadKey();
                $ImageUpload->save();

                Response::Success(__('uploads.ajax-now', ['status' => strtolower(__('global.on'))]));
                break;
            case 'disable':
                if (empty($uploadAllowed->upload_key)) {
                    Response::Success(__('uploads.ajax-already', ['status' => strtolower(__('global.off'))]));
                }

                $uploadAllowed->delete();
                if (is_dir($uploadsFolderPath)) {
                    foreach (new \DirectoryIterator($uploadsFolderPath) as $fileInfo) {
                        if (!$fileInfo->isDot()) {
                            unlink($fileInfo->getPathname());
                        }
                    }
                }

                Response::Success(__('uploads.ajax-now', ['status' => strtolower(__('global.off'))]));
                break;
            default:
                Response::Fail(__('global.ajax-unknown-action', ['action' => $action]));
        }
    }

    public function upload(Request $request)
    {
        $validation_rules = [
            'upload_key' => 'bail|required|string',
            'domain' => 'string',
            'file' => 'bail|required|image',
        ];
        $validator = Validator::make($request->all(array_keys($validation_rules)), $validation_rules);
        if ($validator->fails()) {
            return response()->json($validator->messages(), 400);
        }

        $validated = $validator->valid();
        $secondary_domain = isset($validated['domain']) && $validated['domain'] === config('app.secondary_domain');

        $upload_key = $validated['upload_key'];
        /** @var $upload_allowed ImageUpload */
        $upload_allowed = ImageUpload::where('upload_key', $upload_key)->first();
        if (empty($upload_allowed)) {
            abort(401);
        }

        /** @var $user User */
        $user = $upload_allowed->user()->first();

        $uploaddir = $this->getUploadDirectory();
        if (!@mkdir($uploaddir, 0777, true) && !is_dir($uploaddir)) {
            Response::Fail('Could not create upload directory');
        }

        $file = $validated['file'];
        $orig_file_size = $file->getSize();
        if ($this->_usedSpaceInBytes($user) + $orig_file_size > 524288000) { // 500 mib in bytes
            Response::Fail('Uploading this image would exceed the 500 MiB maximum uploadable amount. Delete some images and try again.');
        }

        $upload = new Upload;
        $upload->generateRandomName();

        $extension = strtolower($file->getClientOriginalExtension());
        $not_animated = $extension !== 'gif';
        if ($not_animated) {
            $extension = 'png';
        }
        $filenames = [
            'full' => $upload->filename.'.'.$extension,
            'preview' => $upload->filename.'p.png',
        ];

        /** @var $image \Intervention\Image\Image */
        $image = Image::make($file);

        $upload->uploaded_by = $user->id;
        $upload->extension = $extension;
        $upload->orig_filename = $file->getClientOriginalName();
        $upload->mimetype = $file->getMimeType();
        $upload->width = $image->width();
        $upload->height = $image->height();
        $upload->secondary_domain = $secondary_domain;

        // Create preview
        $preview_dimensions = min($upload->width, min($upload->height, 300));
        $image->fit($preview_dimensions)->encode('png')->save("$uploaddir/{$filenames['preview']}");

        // Save original
        $file->move($uploaddir, $filenames['full']);
        if ($not_animated) {
            Image::make("$uploaddir/{$filenames['full']}")->encode('png', 0)->save("$uploaddir/{$filenames['full']}");
        }
        $upload->size = filesize("$uploaddir/{$filenames['full']}") + filesize("$uploaddir/{$filenames['preview']}");
        $upload->save();

        return [
            'full' => "{$upload->host}/{$filenames['full']}",
            'preview' => "{$upload->host}/{$filenames['preview']}",
        ];
    }

    public function wipe(Request $request)
    {
        $this->validate($request, [
            'id' => 'bail|required|uuid4',
        ]);
        $id = $request->id;

        /** @var $upload \App\Upload */
        $upload = Upload::find($id);
        if (empty($upload)) {
            Response::Fail(__('uploads.ajax-wipe-notfound'));
        }

        /** @var $user User */
        $user = $upload->uploader()->first();
        $uploadsFolderPath = $this->getUploadDirectory();
        @unlink("$uploadsFolderPath/{$upload->filename}.{$upload->extension}");
        @unlink("$uploadsFolderPath/{$upload->filename}p.{$upload->extension}");

        $upload->delete();
        $out = [];
        $this->_calcOrderBy($request, $out);
        $out['images'] = $this->_getImageArray($user);
        $out['uploadingEnabled'] = true;
        $out['haveResults'] = $out['images']->count() > 0;
        $out['havePreviousPages'] = $out['images']->lastPage() > 0 && $out['images']->currentPage() > $out['images']->lastPage();
        Response::Done([
            'newhtml' => view('partials.uploads-imagelist', $out)->render(),
            'total' => $out['images']->total(),
            'usedSpace' => $this->_usedSpace($user),
        ]);
    }
}
