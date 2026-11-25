<?php

namespace App\Policies;

use App\Models\GeneratedReport;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Support\MunicipalityAccess;

/**
 * Archived period reports are aggregate snapshots, so reading or generating one is
 * not an operational write: read-only oversight roles may use them. Veterinary,
 * evaluator and visitor accounts are excluded, matching their route restrictions.
 *
 * Viewing rechecks every municipality the report covers against the account's
 * current scope, so a stored scope column alone never grants access and a
 * reassigned account loses access to its former area's reports.
 */
class GeneratedReportPolicy
{
    public function __construct(private MunicipalityAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $this->eligible($user);
    }

    public function create(User $user): bool
    {
        return $this->eligible($user);
    }

    public function view(User $user, GeneratedReport $report): bool
    {
        if (! $this->eligible($user)) {
            return false;
        }
        if ($user->isSystemOwner()) {
            return true;
        }

        $scopeAllowed = match ($report->scope_type) {
            'municipality' => true,
            'province' => ($user->requiresProvince() || $user->isRegionalHead())
                && $this->access->scopeProvinces(Province::query(), $user)->whereKey($report->province_id)->exists(),
            'region' => $user->isRegionalHead() && (int) $user->region_id === (int) $report->region_id,
            default => false,
        };
        if (! $scopeAllowed) {
            return false;
        }

        $covered = array_values(array_unique(array_map('intval', (array) $report->municipality_ids)));
        if ($covered === []) {
            // An empty report still belongs to a scope; only its own holder may open it.
            return $report->scope_type !== 'municipality'
                || $user->canAccessMunicipality($report->municipality_id);
        }

        return $this->access->scopeMunicipalities(Municipality::query(), $user)
            ->whereIn('municipalities.id', $covered)->count() === count($covered);
    }

    private function eligible(User $user): bool
    {
        return $user->hasUsableScope()
            && ! $user->isVisitor()
            && ! $user->isGisEvaluator()
            && ! $user->isProvincialVeterinaryOffice();
    }
}
