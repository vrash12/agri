<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureHeadAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || ($user->role ?? null) !== 'head_admin') {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
