<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use App\Util\ShareReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * The public, read-only overview opened through a share link. Invalid, expired and revoked links all look the same.
 */
class ShareController extends Controller
{
    public function show(Request $request, string $token)
    {
        $link = ShareLink::findByToken($token);
        if ($link === null || $link->isExpired()) {
            abort(404);
        }

        $link->update(['view_count' => ($link->view_count ?? 0) + 1, 'last_viewed_at' => now()]);

        // The person reading this is usually not the site owner, so the owner's language settings don't apply
        $lang = in_array($request->query('lang'), ['hu', 'en'], true) ? $request->query('lang') : 'hu';
        App::setLocale($lang);

        return response()
            ->view('share', ['report' => ShareReport::build($link->user, now()->toDateString()), 'lang' => $lang])
            ->withHeaders([
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'Cache-Control' => 'no-store, private',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
            ]);
    }
}
