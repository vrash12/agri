<?php

namespace App\Support;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Schema;

class DashboardCoverage
{
    public function __construct(private MunicipalityAccess $access)
    {
    }

    /**
     * Coverage is measured against the current registry, not eligibility or a
     * historical registry snapshot. Invalid links never count as beneficiaries.
     *
     * @return array<string, int|float|bool|null>
     */
    public function assistance(User $user, int $year): array
    {
        $registered = $this->access->scope(Farmer::query(), $user)->count();
        $releases = $this->access->scope(RiceSeedDistribution::query(), $user);
        $totals = (clone $releases)
            ->whereBetween('rice_seed_distributions.date_received', [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)])
            ->leftJoin('farmers as recipient', function (JoinClause $join) {
                $join->on('recipient.id', '=', 'rice_seed_distributions.farmer_id')
                    ->on('recipient.municipality_id', '=', 'rice_seed_distributions.municipality_id');
            })
            ->selectRaw('COUNT(*) as releases, COUNT(DISTINCT recipient.id) as beneficiaries')
            ->selectRaw('COALESCE(SUM(CASE WHEN recipient.id IS NULL THEN 1 ELSE 0 END), 0) as unlinked')
            ->first();
        $beneficiaries = (int) $totals->beneficiaries;

        return [
            'registered' => $registered,
            'beneficiaries' => $beneficiaries,
            'without_release' => max(0, $registered - $beneficiaries),
            'reach_percent' => $registered > 0 ? round($beneficiaries / $registered * 100, 1) : null,
            'releases' => (int) $totals->releases,
            'unlinked_releases' => (int) $totals->unlinked,
            'undated_releases' => (clone $releases)->whereNull('date_received')->count(),
        ];
    }

    /**
     * Count municipalities, not boundary versions; drafts and multiple active
     * versions do not establish an unambiguous active reference.
     *
     * @return array<string, int|float|bool|null>
     */
    public function geofences(User $user): array
    {
        if (! Schema::hasTable('municipality_boundaries')) {
            return ['available' => false, 'municipalities' => 0, 'covered' => 0, 'missing' => 0, 'ambiguous' => 0, 'coverage_percent' => null];
        }
        $activeBoundaries = $this->access->scope(MunicipalityBoundary::query(), $user)
            ->active()->select('municipality_id')->selectRaw('COUNT(*) as active_count')
            ->groupBy('municipality_id');
        $totals = $this->access->scopeMunicipalities(Municipality::query(), $user)
            ->active()->whereHas('supervisingProvince', fn (Builder $query) => $query->active())
            ->leftJoinSub($activeBoundaries, 'coverage_boundaries', function (JoinClause $join) {
                $join->on('coverage_boundaries.municipality_id', '=', 'municipalities.id');
            })
            ->selectRaw('COUNT(*) as municipalities')
            ->selectRaw('COALESCE(SUM(CASE WHEN coverage_boundaries.active_count = 1 THEN 1 ELSE 0 END), 0) as covered')
            ->selectRaw('COALESCE(SUM(CASE WHEN coverage_boundaries.active_count > 1 THEN 1 ELSE 0 END), 0) as ambiguous')
            ->first();
        $municipalities = (int) $totals->municipalities;
        $covered = (int) $totals->covered;
        $ambiguous = (int) $totals->ambiguous;

        return [
            'available' => true,
            'municipalities' => $municipalities,
            'covered' => $covered,
            'missing' => max(0, $municipalities - $covered - $ambiguous),
            'ambiguous' => $ambiguous,
            'coverage_percent' => $municipalities > 0 ? round($covered / $municipalities * 100, 1) : null,
        ];
    }
}
