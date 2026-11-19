<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Headers for pages opened through a secret link: never cached or indexed, no referrer sent on (the token is in the
 * URL), and a policy that allows this site's own stylesheet, images and video and the single inline theme script (by nonce) only.
 */
trait PrivateSharePage
{
    /**
     * @param  callable(string): SymfonyResponse  $make  Receives the fresh nonce
     */
    private function withSafeHeaders(callable $make): SymfonyResponse
    {
        $nonce = base64_encode(random_bytes(16));

        return $make($nonce)->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; media-src 'self'; script-src 'nonce-$nonce'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
        ]);
    }
}
