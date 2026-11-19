<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PrivateSharePage;
use App\Models\ShareLink;
use App\Util\ShareReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * The public, read-only overview opened through a share link. Any link that cannot be shown gets the same short,
 * friendly page, whatever the reason, instead of the site's normal error page. Both pages use the site's Bootstrap
 * styles and a small theme toggle that starts from the visitor's system preference.
 */
class ShareController extends Controller
{
    use PrivateSharePage;

    public function show(Request $request, string $token = '')
    {
        // The person reading this is usually not the site owner, so the owner's language settings don't apply
        $lang = in_array($request->query('lang'), ['hu', 'en'], true) ? $request->query('lang') : 'hu';
        App::setLocale($lang);

        $link = preg_match('/^[A-Za-z0-9]{'.ShareLink::TOKEN_LENGTH.'}$/', $token) === 1 ? ShareLink::findByToken($token) : null;

        // Expired, revoked, deleted, mistyped or cut short: one and the same page, so nothing can be learned from
        // it about whether a link ever existed. It shows no data and the visit isn't counted.
        if ($link === null || $link->isExpired()) {
            return $this->withSafeHeaders(fn (string $nonce) => response()->view('share-gone', ['lang' => $lang, 'nonce' => $nonce], 404));
        }

        $link->update(['view_count' => ($link->view_count ?? 0) + 1, 'last_viewed_at' => now()]);

        return $this->withSafeHeaders(fn (string $nonce) => response()->view('share', [
            'report' => ShareReport::build($link->user, now()->toDateString()),
            'lang' => $lang,
            'expiresAt' => $link->expires_at?->toDateString(),
            'nonce' => $nonce,
        ]));
    }
}
