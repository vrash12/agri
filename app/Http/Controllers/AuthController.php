<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnforceIdleSession;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
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

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        $remember = $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            AuditTrail::record(
                'login_failed',
                'Authentication',
                'A sign-in attempt failed for '.$validated['email'].'.',
                [
                    'actor_email' => $validated['email'],
                    'metadata' => ['reason' => 'Invalid email or password'],
                ]
            );

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Invalid email or password.',
                ]);
        }

        $request->session()->regenerate();

        /** @var User|null $user */
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Ensure the authenticated account exists
        |--------------------------------------------------------------------------
        */

        if (!$user) {
            AuditTrail::record(
                'login_blocked',
                'Authentication',
                'A sign-in attempt was blocked because the account could not be loaded.',
                [
                    'actor_email' => $validated['email'],
                    'metadata' => ['reason' => 'Authenticated account unavailable'],
                ]
            );
            $this->logoutAuthenticatedUser($request);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Unable to access your account.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate the user's role
        |--------------------------------------------------------------------------
        */

        if (!in_array($user->role, User::ROLES, true)) {
            $this->recordBlockedLogin($user, 'Role is not authorized');
            $this->logoutAuthenticatedUser($request);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Your account is not authorized to access this system.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Block inactive accounts
        |--------------------------------------------------------------------------
        */

        if (!$user->isActive()) {
            $this->recordBlockedLogin($user, 'Account is inactive');
            $this->logoutAuthenticatedUser($request);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Your account is inactive. Please contact the system administrator.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Municipal accounts must have an assigned municipality
        |--------------------------------------------------------------------------
        */

        if ($user->requiresMunicipality() && !$user->municipality_id) {
            $this->recordBlockedLogin($user, 'Municipality is not assigned');
            $this->logoutAuthenticatedUser($request);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Your account is not assigned to a municipality. Please contact the Provincial Agriculture Office.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Ensure the assigned municipality still exists and is active
        |--------------------------------------------------------------------------
        */

        if ($user->requiresMunicipality()) {
            $municipality = $user->municipality;

            if (!$municipality) {
                $this->recordBlockedLogin($user, 'Assigned municipality was not found');
                $this->logoutAuthenticatedUser($request);

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'email' => 'Your assigned municipality could not be found. Please contact the Provincial Agriculture Office.',
                    ]);
            }

            if (
                isset($municipality->is_active) &&
                !$municipality->is_active
            ) {
                $this->recordBlockedLogin($user, 'Assigned municipality is inactive');
                $this->logoutAuthenticatedUser($request);

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'email' => 'Your assigned municipality is currently inactive.',
                    ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Record successful login
        |--------------------------------------------------------------------------
        */

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
        | All users currently use the same dashboard route. The dashboard
        | controller should filter its statistics and records based on the
        | logged-in user's municipality and role.
        |
        */

        return match ($user->role) {
            User::ROLE_SUPER_ADMIN => redirect()->intended(
                route('dashboard')
            ),

            User::ROLE_PROVINCIAL_STAFF => redirect()->intended(
                route('dashboard')
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

    private function recordBlockedLogin(User $user, string $reason): void
    {
        AuditTrail::record(
            'login_blocked',
            'Authentication',
            'A sign-in attempt for '.$user->email.' was blocked.',
            [
                'actor' => $user,
                'auditable' => $user,
                'metadata' => ['reason' => $reason],
            ]
        );
    }
}
