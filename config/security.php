<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | App\Http\Middleware\SecurityHeaders sends this policy on every response.
    |
    | Set SECURITY_CSP_REPORT_ONLY=true to send it as Content-Security-Policy-
    | Report-Only instead, which reports violations in the browser console
    | without blocking anything. Set SECURITY_CSP_ENABLED=false to stop sending
    | it at all. Use those switches if a screen breaks after a deployment, then
    | add the missing source below and turn enforcement back on.
    |
    */

    'csp_enabled' => (bool) env('SECURITY_CSP_ENABLED', true),

    'csp_report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Only sent on requests that already arrived over HTTPS, so a local HTTP
    | environment is never pinned to a certificate it does not have. Raise the
    | age only once HTTPS is confirmed working on every hostname in use.
    |
    */

    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    'hsts_include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),

];
