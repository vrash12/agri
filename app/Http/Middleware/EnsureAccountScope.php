<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountScope
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasUsableScope(), 403,
            'Your account needs an active province or municipality assignment. Contact the System Owner.');

        return $next($request);
    }
}
