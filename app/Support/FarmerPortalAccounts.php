<?php

namespace App\Support;

use App\Models\Farmer;
use App\Models\FarmerPortalAccount;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FarmerPortalAccounts
{
    public function __construct(private ConcurrentWrite $writes, private MunicipalityAccess $access)
    {
    }

    /** @return array{account: FarmerPortalAccount, activationCode: string} */
    public function issue(Farmer $farmer, User $user, string $version): array
    {
        return $this->mutate($farmer, $user, $version, function (Farmer $current, ?FarmerPortalAccount $account) use ($user) {
            $municipalityId = $this->access->resolveForWrite($user, $current->municipality_id);
            $activationCode = Str::random(32);
            $existing = $account !== null;
            $account ??= new FarmerPortalAccount([
                'farmer_id' => $current->id,
                'login_id' => FarmerPortalAccount::canonicalLoginId($current),
            ]);

            // Reissuing is also office-assisted recovery: previous passwords,
            // activation codes, and authenticated sessions cease to be usable.
            $account->fill([
                'municipality_id' => $municipalityId,
                'password' => null,
                'activation_code_hash' => hash('sha256', $activationCode),
                'activation_expires_at' => now()->addHours(24),
                'activated_at' => null,
                'is_active' => true,
                'session_version' => $existing ? $account->session_version + 1 : 1,
            ])->save();

            AuditTrail::record('farmer_portal_activation_issued', 'Farmer portal', 'Staff issued a farmer portal activation code after verifying identity.', [
                'actor' => $user,
                'auditable' => $account,
                'municipality_id' => $municipalityId,
                'metadata' => ['identity_verified' => true, 'reissued' => $existing],
            ]);

            return ['account' => $account->refresh(), 'activationCode' => $activationCode];
        });
    }

    public function disable(Farmer $farmer, User $user, string $version): FarmerPortalAccount
    {
        return $this->mutate($farmer, $user, $version, function (Farmer $current, ?FarmerPortalAccount $account) use ($user) {
            if (! $account) {
                throw ValidationException::withMessages(['_record_version' => 'This farmer has no portal account to disable. Reload this page.']);
            }

            $account->fill([
                'is_active' => false,
                'password' => null,
                'activated_at' => null,
                'activation_code_hash' => null,
                'activation_expires_at' => null,
                'session_version' => $account->session_version + 1,
            ])->save();

            AuditTrail::record('farmer_portal_disabled', 'Farmer portal', 'Staff disabled a farmer portal account and revoked its sessions.', [
                'actor' => $user,
                'auditable' => $account,
                'municipality_id' => $current->municipality_id,
            ]);

            return $account->refresh();
        });
    }

    /**
     * The parent lock prevents two first-time issuances and rechecks ownership.
     * A pre-creation farmer token becomes stale as soon as an account exists.
     *
     * @template TReturn
     *
     * @param  Closure(Farmer, ?FarmerPortalAccount): TReturn  $callback
     * @return TReturn
     */
    private function mutate(Farmer $farmer, User $user, string $version, Closure $callback)
    {
        Gate::forUser($user)->authorize('update', $farmer);

        return $this->writes->locked($farmer, function (Farmer $current) use ($user, $version, $callback) {
            Gate::forUser($user)->authorize('update', $current);
            $account = FarmerPortalAccount::query()->where('farmer_id', $current->id)->lockForUpdate()->first();

            return $this->writes->execute($account ?? $current, $version, function (Model $locked) use ($current, $callback) {
                return $callback($current, $locked instanceof FarmerPortalAccount ? $locked : null);
            });
        });
    }
}
