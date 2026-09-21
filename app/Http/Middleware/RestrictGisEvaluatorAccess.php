<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictGisEvaluatorAccess
{
    /**
     * Keep external GIS evaluators inside the non-operational boundary viewer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isGisEvaluator()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $allowed = [
            'municipality-boundaries.index',
            'municipality-boundaries.data',
            'municipality-boundaries.barangays',
        ];

        if (is_string($routeName) && in_array($routeName, $allowed, true)) {
            return $next($request);
        }

        $message = 'Your GIS Evaluator account can access only the read-only administrative boundary viewer.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => 'GIS_EVALUATOR_SCOPE_ONLY',
            ], 403);
        }

        return redirect()->route('municipality-boundaries.index')->with('error', $message);
    }
}
