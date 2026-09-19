<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnforceIdleSession;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Unsuccessful sign-ins allowed for one email address from one IP before a lockout.
     */
    private const MAX_SIGN_IN_ATTEMPTS = 5;

    /**
     * How long the lockout lasts, and how long attempts are remembered.
     */
    private const LOCKOUT_SECONDS = 300;

    /**
     * Unsuccessful sign-in entries written for one client address before the rest
     * of the window is summarised into a single entry.
     *
     * The per-address lockout above bounds guessing at one email. Working through
     * many addresses instead still produces one audit row per attempt, so the
     * audit trail needs its own ceiling: reading it should not mean scrolling past
     * thousands of generated entries to find the real one.
     */
    private const FAILURE_AUDIT_LIMIT = 20;

    private const FAILURE_AUDIT_WINDOW = 900;

    /**
     * Display the login page.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate the user.
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                // Matches the users column, and stops an oversized value reaching the audit trail.
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
            'remember' => [
                'nullable',
                'boolean',
            ],
        ]);

        $email = $validated['email'];
        $this->ensureSignInIsNotThrottled($request, $email);

        $credentials = [
            'email' => $email,
            'password' => $validated['password'],
        ];

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            $this->auditUnsuccessfulSignIn(
                $request,
                'login_failed',
                'A sign-in attempt failed for '.$email.'.',
                [
                    'actor_email' => $email,
                    'metadata' => ['reason' => 'Invalid email or password'],
                ]
            );

            return $this->refuseSignIn($request, $email, 'Invalid email or password.');
        }

        $request->session()->regenerate();

        /** @var User|null $user */
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Ensure the authenticated account exists
        |--------------------------------------------------------------------------
        */

        if (! $user) {
            $this->auditUnsuccessfulSignIn(
                $request,
                'login_blocked',
                'A sign-in attempt was blocked because the account could not be loaded.',
                [
                    'actor_email' => $email,
                    'metadata' => ['reason' => 'Authenticated account unavailable'],
                ]
            );
            $this->logoutAuthenticatedUser($request);

            return $this->refuseSignIn($request, $email, 'Unable to access your account.');
        }

        /*
        |--------------------------------------------------------------------------
        | Validate the user's role
        |--------------------------------------------------------------------------
        */

        if (! in_array($user->role, User::ROLES, true)) {
            $this->recordBlockedLogin($request, $user, 'Role is not authorized');
            $this->logoutAuthenticatedUser($request);

            return $this->refuseSignIn($request, $email, 'Your account is not authorized to access this system.');
        }

        /*
        |--------------------------------------------------------------------------
        | Block inactive accounts
        |--------------------------------------------------------------------------
        */

        if (! $user->isActive()) {
            $this->recordBlockedLogin($request, $user, 'Account is inactive');
            $this->logoutAuthenticatedUser($request);

            return $this->refuseSignIn($request, $email, 'Your account is inactive. Please contact the system administrator.');
        }

        /*
        |--------------------------------------------------------------------------
        | Municipal accounts must have an assigned municipality
        |--------------------------------------------------------------------------
        */

        if ($user->requiresMunicipality() && ! $user->municipality_id) {
            $this->recordBlockedLogin($request, $user, 'Municipality is not assigned');
            $this->logoutAuthenticatedUser($request);

            return $this->refuseSignIn($request, $email, 'Your account is not assigned to a municipality. Please contact the Provincial Agriculture Office.');
        }

        /*
        |--------------------------------------------------------------------------
        | Ensure the assigned municipality still exists and is active
        |--------------------------------------------------------------------------
        */

        if ($user->requiresMunicipality()) {
            $municipality = $user->municipality;

            if (! $municipality) {
                $this->recordBlockedLogin($request, $user, 'Assigned municipality was not found');
                $this->logoutAuthenticatedUser($request);

                return $this->refuseSignIn($request, $email, 'Your assigned municipality could not be found. Please contact the Provincial Agriculture Office.');
            }

            if (
                isset($municipality->is_active) &&
                ! $municipality->is_active
            ) {
                $this->recordBlockedLogin($request, $user, 'Assigned municipality is inactive');
                $this->logoutAuthenticatedUser($request);

                return $this->refuseSignIn($request, $email, 'Your assigned municipality is currently inactive.');
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Record successful login
        |--------------------------------------------------------------------------
        */

        if (! $user->hasUsableScope()) {
            $this->recordBlockedLogin($request, $user, 'Province or municipality scope is unavailable');
            $this->logoutAuthenticatedUser($request);

            return $this->refuseSignIn($request, $email, 'Your account needs an active province or municipality assignment. Contact the System Owner.');
        }

        RateLimiter::clear($this->throttleKey($request, $email));

        // A browser session belongs to one audience at a time.
        Auth::guard('farmer')->logout();
        $request->session()->forget([
            \App\Support\FarmerPortalAuthentication::VERSION_KEY,
            \App\Support\FarmerPortalAuthentication::ACTIVITY_KEY,
        ]);

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        AuditTrail::record(
            'login',
            'Authentication',
            $user->name.' signed in successfully.',
            [
                'actor' => $user,
                'auditable' => $user,
                'metadata' => ['remembered_session' => $remember],
            ]
        );

        $request->session()->put(
            EnforceIdleSession::LAST_ACTIVITY_KEY,
            now()->timestamp
        );

        /*
        |--------------------------------------------------------------------------
        | Redirect based on role
        |--------------------------------------------------------------------------
        |
        | Agriculture roles use the dashboard. Provincial Veterinary Office
        | accounts enter the only module authorized for that role.
        |
        */

        return match ($user->role) {
            User::ROLE_SYSTEM_OWNER => redirect()->intended(route('dashboard')),
            User::ROLE_SUPER_ADMIN => redirect()->intended(
                route('dashboard')
            ),

            User::ROLE_PROVINCIAL_STAFF => redirect()->intended(
                route('dashboard')
            ),

            User::ROLE_PROVINCIAL_VET => redirect()->route(
                'anti-rabies-vaccinations.index'
            ),

            User::ROLE_MUNICIPAL_HEAD => redirect()->intended(
                route('dashboard')
            ),

            User::ROLE_MUNICIPAL_STAFF => redirect()->intended(
                route('dashboard')
            ),

            default => redirect()->route('login'),
        };
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            AuditTrail::record(
                'logout',
                'Authentication',
                $user->name.' signed out.',
                [
                    'actor' => $user,
                    'auditable' => $user,
                ]
            );
        }

        $this->logoutAuthenticatedUser($request);

        return redirect()
            ->route('login')
            ->with('success', 'You have been logged out successfully.');
    }

    /**
     * End a session after the browser detects fifteen minutes of inactivity.
     */
    public function timeout(Request $request): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            AuditTrail::record(
                'session_timeout',
                'Authentication',
                $user->name.' was signed out after '
                    .EnforceIdleSession::timeoutMinutes().' minutes of inactivity.',
                [
                    'actor' => $user,
                    'auditable' => $user,
                    'metadata' => [
                        'reason' => 'idle_timeout',
                        'idle_limit_seconds' => EnforceIdleSession::timeoutMinutes() * 60,
                    ],
                ]
            );
        }

        $this->logoutAuthenticatedUser($request);

        $redirect = route('login', ['timeout' => 1]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => EnforceIdleSession::timeoutMessage(),
                'code' => 'SESSION_IDLE_TIMEOUT',
                'redirect' => $redirect,
            ]);
        }

        return redirect($redirect)
            ->with('error', EnforceIdleSession::timeoutMessage());
    }

    /**
     * Safely terminate the authenticated session.
     */
    private function logoutAuthenticatedUser(Request $request): void
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * Stop credential guessing before the password is ever checked.
     *
     * The key is per email address and IP, so one attacker cannot lock a real
     * account out of the system by guessing against it from somewhere else.
     *
     * @throws ValidationException
     */
    private function ensureSignInIsNotThrottled(Request $request, string $email): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $email), self::MAX_SIGN_IN_ATTEMPTS)) {
            return;
        }

        event(new Lockout($request));
        $seconds = RateLimiter::availableIn($this->throttleKey($request, $email));

        throw ValidationException::withMessages([
            'email' => 'Too many sign-in attempts. Try again in '.ceil($seconds / 60).' minute(s).',
        ]);
    }

    /**
     * Refuse one sign-in attempt and count it toward the lockout.
     */
    private function refuseSignIn(Request $request, string $email, string $message): RedirectResponse
    {
        $key = $this->throttleKey($request, $email);
        RateLimiter::hit($key, self::LOCKOUT_SECONDS);

        // Audited once, as the lockout begins, so a flood cannot fill the audit trail.
        if (RateLimiter::attempts($key) === self::MAX_SIGN_IN_ATTEMPTS) {
            $this->auditUnsuccessfulSignIn(
                $request,
                'login_throttled',
                'Sign-in attempts for '.$email.' were temporarily blocked after repeated failures.',
                [
                    'actor_email' => $email,
                    'metadata' => [
                        'reason' => 'Too many unsuccessful sign-in attempts',
                        'attempts' => self::MAX_SIGN_IN_ATTEMPTS,
                        'lockout_seconds' => self::LOCKOUT_SECONDS,
                    ],
                ]
            );
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
    }

    /**
     * Rate-limiter key for one email address from one client address.
     */
    private function throttleKey(Request $request, string $email): string
    {
        return 'sign-in:'.Str::transliterate(Str::lower(trim($email))).'|'.$request->ip();
    }

    private function recordBlockedLogin(Request $request, User $user, string $reason): void
    {
        $this->auditUnsuccessfulSignIn(
            $request,
            'login_blocked',
            'A sign-in attempt for '.$user->email.' was blocked.',
            [
                'actor' => $user,
                'auditable' => $user,
                'metadata' => ['reason' => $reason],
            ]
        );
    }

    /**
     * Write one unsuccessful sign-in entry, with a ceiling per client address.
     *
     * Working through many email addresses would otherwise write one row per
     * attempt for as long as the attacker keeps going. Once an address passes the
     * ceiling, a single entry records that the rest of the window was suppressed,
     * so the trail still shows what happened without burying the genuine entries.
     * Successful sign-ins, logouts, and session timeouts are never suppressed.
     *
     * @param  array<string,mixed>  $context
     */
    private function auditUnsuccessfulSignIn(Request $request, string $event, string $description, array $context): void
    {
        $key = 'sign-in-audit:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::FAILURE_AUDIT_LIMIT)) {
            return;
        }

        RateLimiter::hit($key, self::FAILURE_AUDIT_WINDOW);

        if (RateLimiter::attempts($key) === self::FAILURE_AUDIT_LIMIT) {
            AuditTrail::record(
                'login_failures_suppressed',
                'Authentication',
                'Repeated unsuccessful sign-ins came from one address; further entries are suppressed for '
                    .(int) (self::FAILURE_AUDIT_WINDOW / 60).' minutes.',
                [
                    'metadata' => [
                        'reason' => 'Unsuccessful sign-in entries exceeded the per-address ceiling',
                        'entries' => self::FAILURE_AUDIT_LIMIT,
                        'window_seconds' => self::FAILURE_AUDIT_WINDOW,
                    ],
                ]
            );

            return;
        }

        AuditTrail::record($event, 'Authentication', $description, $context);
    }
}
