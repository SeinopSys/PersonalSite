<?php

namespace App\Http\Middleware;

use App\Util\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the Content-Security-Policy in report-only mode (see App\Util\ContentSecurityPolicy) with the report endpoint from
 * `csp.report_uri`; nothing is sent while that is empty. Pages that set a policy of their own (the share pages, which
 * enforce a stricter one) are left alone.
 */
class AddContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $reportUri = (string) config('csp.report_uri', '');
        if ($reportUri === '' || !$this->isHtmlDocument($response)) {
            return $response;
        }

        $hashes = ContentSecurityPolicy::inlineScriptHashes((string) $response->getContent());
        $response->headers->set('Content-Security-Policy-Report-Only', ContentSecurityPolicy::build($hashes, $reportUri));
        $response->headers->set('Reporting-Endpoints', ContentSecurityPolicy::reportingEndpoints($reportUri));

        return $response;
    }

    /** Only full pages carry scripts: not JSON (the API), redirects, downloads or pages with their own policy. */
    private function isHtmlDocument(Response $response): bool
    {
        if ($response->getStatusCode() === 304 || $response->isRedirection() || $response->headers->has('Content-Security-Policy')) {
            return false;
        }

        return str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html');
    }
}
