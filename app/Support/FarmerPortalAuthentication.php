<?php

namespace App\Support;

use App\Models\Farmer;
use App\Models\FarmerPortalAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class FarmerPortalAuthentication
{
    public const VERSION_KEY = 'farmer_portal.version';

    public const ACTIVITY_KEY = 'farmer_portal.last_activity_at';

    public function login(Request $request): void
    {
        $identifier = trim((string) $request->input('login_id'));
        $account = $this->findAccount($identifier);
        $keys = $this->throttleKeys($request, $identifier, $account);
        $this->checkThrottle($keys);
        // Perform a password hash comparison even when no account exists.
        $passwordHash = $account?->password ?: '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = Hash::check((string) $request->input('password'), $passwordHash);
        if (! $account || ! $valid || ! $account->activated_at || ! $account->password || ! $account->hasUsableScope()) {
            $this->fail($request, $keys, 'The login details could not be verified. Please try again or contact your agriculture office.');
        }
        // Prevent a concurrent staff reset from authenticating a stale password snapshot.
        $account = DB::transaction(function () use ($account, $request, $keys) {
            $current = FarmerPortalAccount::query()->lockForUpdate()->find($account->id);
            if (! $current || $current->session_version !== $account->session_version || $current->password !== $account->password || ! $current->hasUsableScope()) {
                $this->fail($request, $keys, 'The login details could not be verified. Please try again or contact your agriculture office.');
            }
            $current->forceFill(['last_login_at' => now()])->save();

            return $current;
        }, 3);
        foreach ($keys as $key) {
            RateLimiter::clear($key);
        }
        $this->startSession($request, $account);
        $this->audit('farmer_portal_login', $account, 'Farmer signed in to their portal.');
    }

    public function activate(Request $request): void
    {
        $identifier = strtoupper(trim((string) $request->input('login_id')));
        $account = FarmerPortalAccount::query()->where('login_id', $identifier)->first();
        $keys = $this->throttleKeys($request, $identifier, $account);
        $this->checkThrottle($keys);
        $password = Hash::make((string) $request->input('password'));
        $activated = DB::transaction(function () use ($account, $request, $password) {
            $current = $account ? FarmerPortalAccount::query()->lockForUpdate()->find($account->id) : null;
            if (! $current || ! $current->hasUsableScope() || ! $current->activation_code_hash
                || ! $current->activation_expires_at || $current->activation_expires_at->isPast()
                || ! hash_equals($current->activation_code_hash, hash('sha256', (string) $request->input('activation_code')))) {
                return null;
            }
            $current->forceFill([
                'password' => $password, 'activated_at' => now(), 'activation_code_hash' => null,
                'activation_expires_at' => null, 'session_version' => $current->session_version + 1,
            ])->save();

            return $current;
        }, 3);
        if (! $activated) {
            $this->fail($request, $keys, 'The activation details could not be verified. Ask your agriculture office for a new code.');
        }
        foreach ($keys as $key) {
            RateLimiter::clear($key);
        }
        $this->audit('farmer_portal_activated', $activated, 'Farmer activated their portal password.');
    }

    private function findAccount(string $identifier): ?FarmerPortalAccount
    {
        if (str_starts_with(strtoupper($identifier), 'AGRI-F-')) {
            return FarmerPortalAccount::query()->where('login_id', strtoupper($identifier))->first();
        }
        // Registry IDs are not globally unique in legacy imports. Never pick the
        // first match or combine records, including records in another municipality.
        $farmers = Farmer::query()->where('rsbsa_no', $identifier)->limit(2)->pluck('id');

        return $farmers->count() === 1
            ? FarmerPortalAccount::query()->where('farmer_id', $farmers->first())->first()
            : null;
    }

    private function startSession(Request $request, FarmerPortalAccount $account): void
    {
        Auth::guard('web')->logout();
        Auth::guard('farmer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('farmer')->login($account, false);
        $request->session()->regenerate();
        $request->session()->put(self::VERSION_KEY, $account->session_version);
        $request->session()->put(self::ACTIVITY_KEY, now()->timestamp);
    }

    public function logout(Request $request, string $event = 'farmer_portal_logout'): void
    {
        $account = Auth::guard('farmer')->user();
        if ($account instanceof FarmerPortalAccount) {
            $this->audit($event, $account, 'Farmer portal session ended.');
        }
        Auth::guard('farmer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function audit(string $event, FarmerPortalAccount $account, string $description): void
    {
        AuditTrail::record($event, 'Farmer portal', $description, [
            'actor' => $account, 'actor_role' => 'farmer', 'actor_name' => 'Farmer portal account',
            'auditable' => $account, 'municipality_id' => $account->municipality_id,
        ]);
    }

    private function throttleKeys(Request $request, string $identifier, ?FarmerPortalAccount $account): array
    {
        $keys = ['farmer-login:'.hash('sha256', strtolower($identifier).'|'.$request->ip())];
        if ($account) {
            $keys[] = 'farmer-account-login:'.$account->id;
        }

        return $keys;
    }

    private function checkThrottle(array $keys): void
    {
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw ValidationException::withMessages(['login_id' => 'Too many attempts. Wait five minutes, then try again.']);
            }
        }
    }

    private function fail(Request $request, array $keys, string $message): void
    {
        foreach ($keys as $key) {
            RateLimiter::hit($key, 300);
        }
        $auditKey = 'farmer-login-audit:'.hash('sha256', (string) $request->ip());
        if (! RateLimiter::tooManyAttempts($auditKey, 20)) {
            RateLimiter::hit($auditKey, 900);
            AuditTrail::record('farmer_portal_login_failed', 'Farmer portal', 'Farmer portal credentials could not be verified.', ['owner_only' => true]);
        }
        throw ValidationException::withMessages(['login_id' => $message]);
    }
}
