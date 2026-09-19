<?php

namespace App\Http\Middleware;

use App\Models\FarmerPortalAccount;
use App\Support\FarmerPortalAuthentication;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFarmerPortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Never replace the office guard: existing policies accept staff User models.
        Auth::shouldUse('web');
        $account = Auth::guard('farmer')->user();
        $account = $account instanceof FarmerPortalAccount ? $account->fresh() : null;
        $lastActivity = $request->session()->get(FarmerPortalAuthentication::ACTIVITY_KEY);
        $version = $request->session()->get(FarmerPortalAuthentication::VERSION_KEY);
        $expired = ! is_numeric($lastActivity) || now()->timestamp - (int) $lastActivity >= EnforceIdleSession::timeoutMinutes() * 60;
        if (! $account instanceof FarmerPortalAccount || ! $account->hasUsableScope() || ! $account->activated_at
            || ! $account->password || (int) $version !== $account->session_version || $expired) {
            if (Auth::guard('farmer')->check()) {
                app(FarmerPortalAuthentication::class)->logout($request, 'farmer_portal_session_ended');
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please sign in to the farmer portal again.', 'redirect' => route('farmer-portal.login')], 401);
            }

            return redirect()->route('farmer-portal.login')->with('error', 'Please sign in to the farmer portal again.');
        }
        $request->session()->put(FarmerPortalAuthentication::ACTIVITY_KEY, now()->timestamp);
        \Illuminate\Support\Facades\Gate::forUser($account)->authorize('view', $account);
        $request->attributes->set('farmerPortalAccount', $account);

        return $next($request);
    }
}
