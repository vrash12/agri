<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RestrictVisitorAccess
{
    /**
     * Keep visitor Super Admin accounts inside the non-operational boundary viewer.
     *
     * The controller and policy checks repeat this boundary, but the middleware is
     * the first line of defence for every route, including guessed URLs and JSON
     * endpoints that do not render the navigation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isVisitor()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $allowed = [
            'dashboard',
            'municipality-boundaries.index',
            'municipality-boundaries.data',
            'municipality-boundaries.barangays',
        ];

        if (is_string($routeName) && in_array($routeName, $allowed, true)) {
            return $next($request);
        }

        $message = 'Visitor accounts can access only the read-only municipality boundary viewer.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => 'VISITOR_SCOPE_ONLY',
            ], 403);
        }

        return redirect()->route('municipality-boundaries.index')->with('error', $message);
    }
}
