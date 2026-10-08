<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use App\Models\User;
use App\Util\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Creating, listing and revoking the share links of the signed-in user.
 */
class ShareLinksController extends Controller
{
    private static function linkJson(ShareLink $link): array
    {
        return [
            'id' => $link->id,
            'label' => $link->label,
            'url' => url('/share/'.$link->token),
            'expires_at' => $link->expires_at?->toDateString(),
            'expired' => $link->isExpired(),
            'view_count' => $link->view_count ?? 0,
            'last_viewed_at' => $link->last_viewed_at?->toDateString(),
            'created_at' => $link->created_at->toDateString(),
        ];
    }

    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        return Response::Done([
            'links' => $user->shareLinks()->get()->sortByDesc('created_at')->map(fn (ShareLink $l) => self::linkJson($l))->values(),
        ]);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'expires_in_days' => 'nullable|integer|in:7,30,90,365',
        ]);

        $link = ShareLink::create([
            'user_id' => $user->id,
            'token' => ShareLink::makeToken(),
            'label' => $validated['label'] ?? null,
            'expires_at' => isset($validated['expires_in_days']) ? now()->addDays($validated['expires_in_days'])->startOfDay() : null,
            'view_count' => 0,
        ]);

        return Response::Done(['link' => self::linkJson($link)]);
    }

    public function destroy(string $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $link = $user->shareLinks()->where('id', $id)->first();
        if ($link === null) {
            return Response::Fail(__('bills.share-not-found'));
        }
        $link->delete();

        return Response::Done();
    }
}
