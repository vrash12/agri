<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictProvincialVeterinaryAccess
{
    /**
     * Keep Provincial Veterinary Office accounts inside Animal Health.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || ! $user->isProvincialVeterinaryOffice()
        ) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (
            is_string($routeName)
            && str_starts_with(
                $routeName,
                'anti-rabies-vaccinations.'
            )
        ) {
            return $next($request);
        }

        $message = 'Your Provincial Veterinary Office account can access only the Animal Health workspace.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => 'PROVINCIAL_VET_SCOPE_ONLY',
            ], 403);
        }

        return redirect()
            ->route('anti-rabies-vaccinations.index')
            ->with('error', $message);
    }
}
