<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    protected function addCookieToResponse($request, $response)
    {
        // Verification still ran before chat. A delayed read-only reply must
        // not replace the CSRF cookie created by a concurrent logout/login.
        if ($request->routeIs('assistant.message')
            && $request->attributes->get('assistant_session_committed') === true) {
            return $response;
        }

        return parent::addCookieToResponse($request, $response);
    }
}
