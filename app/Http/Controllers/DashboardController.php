<?php

namespace App\Http\Controllers;

use App\Util\Core;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $data = ['title' => __('global.dashboard'), 'js' => ['dashboard']];

        $imageUpload = $user->rootImageUploadKey()->first();
        $data['uploadingEnabled'] = !empty($imageUpload);

        return view('dashboard', $data);
    }

    public function statsUploads(): JsonResponse
    {
        $user = Auth::user();
        $imageUpload = $user->rootImageUploadKey()->first();
        if (empty($imageUpload)) {
            return response()->json(['error' => 'not_enabled']);
        }

        $usedBytes  = (int)$user->uploads()->sum('size');
        $quotaBytes = (int)config('app.upload_quota_bytes');

        $uploads = $user->uploads()
            ->orderByDesc('uploaded_at')
            ->limit(4)
            ->get()
            ->map(fn($u) => [
                'preview' => $u->host . '/' . $u->filename . 'p.png',
                'full'    => $u->host . '/' . $u->filename . '.' . $u->extension,
                'name'    => $u->orig_filename,
            ])
            ->values()
            ->toArray();

        return response()->json([
            'usedSpace'  => Core::ReadableFilesize($usedBytes),
            'quotaSpace' => Core::ReadableFilesize($quotaBytes),
            'usedPct'    => $quotaBytes > 0 ? min(100, (int)round($usedBytes / $quotaBytes * 100)) : 0,
            'uploads'    => $uploads,
        ]);
    }

    public function account(Request $request)
    {
        $user = Auth::user();
        return view('account', [
            'title'          => __('global.account'),
            'twoFactorSetup' => $user->hasTwoFactorEnabled()
                ? null
                : TwoFactorAuthController::pendingSetup($request, $user),
        ]);
    }

    public function saveProfile(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->name  = $validated['name'];
        $user->email = strtolower($validated['email']);
        $user->save();

        return redirect('/account')->with('success', __('dashboard.profile-saved'));
    }
}
