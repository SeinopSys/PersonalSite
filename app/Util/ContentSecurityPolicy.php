<?php

namespace App\Util;

/**
 * Builds the Content-Security-Policy sent in report-only mode (violations go to the report endpoint, nothing is blocked).
 *
 * Scripts may come from this site, from cdnjs (jQuery and Bootstrap, which also carry integrity hashes) and from the
 * inline scripts the page itself contains, which are allowed by their SHA-256 hash. The hashes are computed from the
 * final response body, so they are right for the inline script that carries the per-request CSRF token as well.
 */
final class ContentSecurityPolicy
{
    /** Script types a browser executes (no type attribute, or one of these); everything else, e.g. application/json, is data. */
    private const EXECUTABLE_SCRIPT_TYPES = ['', 'module', 'text/javascript', 'application/javascript', 'text/ecmascript', 'application/ecmascript'];

    /**
     * SHA-256 source expressions ('sha256-...') of every executable inline script in the HTML, without duplicates.
     *
     * @return list<string>
     */
    public static function inlineScriptHashes(string $html): array
    {
        if (!preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $html, $scripts, PREG_SET_ORDER)) {
            return [];
        }

        $hashes = [];
        foreach ($scripts as [, $attributes, $content]) {
            if (preg_match('/\bsrc\s*=/i', $attributes)) {
                continue; // external, covered by the source list
            }
            $type = preg_match('/\btype\s*=\s*["\']?([^"\'\s>]*)/i', $attributes, $match) ? strtolower(trim($match[1])) : '';
            if (!in_array($type, self::EXECUTABLE_SCRIPT_TYPES, true) || trim($content) === '') {
                continue;
            }
            $hashes["'sha256-" . base64_encode(hash('sha256', $content, true)) . "'"] = true;
        }

        return array_keys($hashes);
    }

    /**
     * @param list<string> $scriptHashes Source expressions from inlineScriptHashes()
     */
    public static function build(array $scriptHashes, string $reportUri): string
    {
        return implode('; ', [
            "default-src 'self'",
            // 'wasm-unsafe-eval': the Friendly Captcha widget solves its puzzle with WebAssembly in a worker
            implode(' ', ["script-src 'self' https://cdnjs.cloudflare.com 'wasm-unsafe-eval'", ...$scriptHashes]),
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            // s.gravatar.com: the navbar avatar of a logged-in user (User::getGravatar), gravatar.com: the about page
            "img-src 'self' data: https://gravatar.com https://www.gravatar.com https://s.gravatar.com",
            "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com",
            "connect-src 'self' https://api.friendlycaptcha.com https://eu-api.friendlycaptcha.eu",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "report-uri $reportUri",
            'report-to csp-endpoint',
        ]);
    }

    /** Value of the Reporting-Endpoints header that the policy's `report-to csp-endpoint` refers to. */
    public static function reportingEndpoints(string $reportUri): string
    {
        return 'csp-endpoint="' . $reportUri . '"';
    }
}
