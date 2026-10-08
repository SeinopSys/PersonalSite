<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use App\Util\ShareReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * The public, read-only overview opened through a share link. Unknown and revoked links are a plain 404; an expired
 * link gets a short friendly page instead.
 */
class ShareController extends Controller
{
    public function show(Request $request, string $token)
    {
        $link = ShareLink::findByToken($token);
        // Unknown and revoked links are indistinguishable: both are just a 404
        if ($link === null) {
            abort(404);
        }

        // The person reading this is usually not the site owner, so the owner's language settings don't apply
        $lang = in_array($request->query('lang'), ['hu', 'en'], true) ? $request->query('lang') : 'hu';
        App::setLocale($lang);

        // Only someone holding the full secret gets here, so saying the link has expired reveals nothing new.
        // No data is shown and the visit isn't counted.
        if ($link->isExpired()) {
            return $this->withSafeHeaders(response()->view('share-expired', [
                'lang' => $lang,
                'expiredOn' => $link->expires_at->toDateString(),
            ], 410));
        }

        $link->update(['view_count' => ($link->view_count ?? 0) + 1, 'last_viewed_at' => now()]);

        return $this->withSafeHeaders(response()->view('share', [
            'report' => ShareReport::build($link->user, now()->toDateString()),
            'lang' => $lang,
            'expiresAt' => $link->expires_at?->toDateString(),
        ]));
    }

    private function withSafeHeaders(Response $response): Response
    {
        return $response->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
        ]);
    }
}
