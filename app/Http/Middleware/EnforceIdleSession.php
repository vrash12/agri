<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleSession
{
    public const LAST_ACTIVITY_KEY = 'auth.last_activity_at';

    /**
     * End an authenticated session that has exceeded the configured idle limit.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $now = now()->timestamp;
        $lastActivity = $request->session()->get(self::LAST_ACTIVITY_KEY);
        $idleSeconds = self::timeoutMinutes() * 60;

        if (is_numeric($lastActivity) && ($now - (int) $lastActivity) >= $idleSeconds) {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user) {
                AuditTrail::record(
                    'session_timeout',
                    'Authentication',
                    $user->name.' was signed out after '.self::timeoutMinutes().' minutes of inactivity.',
                    [
                        'actor' => $user,
                        'auditable' => $user,
                        'metadata' => [
                            'reason' => 'idle_timeout',
                            'idle_limit_seconds' => $idleSeconds,
                        ],
                    ]
                );
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => self::timeoutMessage(),
                    'code' => 'SESSION_IDLE_TIMEOUT',
                    'redirect' => route('login', ['timeout' => 1]),
                ], 401);
            }

            return redirect()
                ->route('login', ['timeout' => 1])
                ->with('error', self::timeoutMessage());
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, $now);

        return $next($request);
    }

    public static function timeoutMinutes(): int
    {
        return max(1, (int) config('session.idle_timeout', 15));
    }

    public static function timeoutMessage(): string
    {
        return 'Your session ended after '.self::timeoutMinutes()
            .' minutes of inactivity. Please sign in again.';
    }
}
