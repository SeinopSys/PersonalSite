<?php

return [
    /*
     * Content-Security-Policy in report-only mode (App\Util\ContentSecurityPolicy). Violations are posted to this
     * address; with it empty no policy is sent.
     */
    'report_uri' => env('CSP_REPORT_URI', ''),
];
