<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class AssistanceCoverage
{
    public const MUNICIPALITY_LIMIT = 250;

    public function __construct(private MunicipalityAccess $access)
    {
    }

    public function provinces(User $user): Collection
    {
        return Province::query()->active()
            ->whereHas('municipalities', fn (Builder $query) => $this->access->scopeMunicipalities($query->active(), $user))
            ->orderBy('name')->limit(150)->get(['id', 'name']);
    }

    public function municipalities(User $user, int $provinceId): Builder
    {
        return $this->access->scopeMunicipalities(Municipality::query()->active(), $user)
            ->where('province_id', $provinceId)
            ->whereHas('supervisingProvince', fn (Builder $query) => $query->active());
    }

    /** All aggregation starts with the authorized, selected municipality IDs. */
    private function releases(User $user, array $ids): Builder
    {
        return $this->access->scope(RiceSeedDistribution::query(), $user)
            ->whereIn('rice_seed_distributions.municipality_id', $ids)
            ->leftJoin('rice_distribution_batches as coverage_batch', function (JoinClause $join) {
                $join->on('coverage_batch.id', '=', 'rice_seed_distributions.batch_id')
                    ->on('coverage_batch.municipality_id', '=', 'rice_seed_distributions.municipality_id')
                    // Only rice releases can legitimately belong to rice sheets.
                    ->where('rice_seed_distributions.input_category', '=', 'rice_seed');
            })
            ->leftJoin('farmers as coverage_farmer', function (JoinClause $join) {
                $join->on('coverage_farmer.id', '=', 'rice_seed_distributions.farmer_id')
                    ->on('coverage_farmer.municipality_id', '=', 'rice_seed_distributions.municipality_id');
            });
    }

    private function knownPeriod(Builder $query): void
    {
        $query->whereIn('coverage_batch.planting_season', ['dry', 'wet'])
            ->whereBetween('coverage_batch.planting_year', [1990, now()->year + 1]);
    }

    private function unknownPeriod(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereNull('coverage_batch.planting_season')
                ->orWhereNotIn('coverage_batch.planting_season', ['dry', 'wet'])
                ->orWhereNull('coverage_batch.planting_year')
                ->orWhere('coverage_batch.planting_year', '<', 1990)
                ->orWhere('coverage_batch.planting_year', '>', now()->year + 1);
        });
    }

    /** @return array<string, mixed> */
    public function report(User $user, Collection $municipalities, array $filters): array
    {
        $ids = $municipalities->pluck('id')->all();
        $base = $this->releases($user, $ids);
        if ($filters['category']) {
            $base->where('rice_seed_distributions.input_category', $filters['category']);
        }
        $references = (clone $base)->whereNotNull('coverage_batch.reference')
            ->where('coverage_batch.reference', '!=', '')
            ->select('coverage_batch.reference')->distinct()->orderBy('coverage_batch.reference')
            ->limit(201)->pluck('reference');
        if ($filters['reference'] !== '') {
            $base->where('coverage_batch.reference', $filters['reference']);
        }

        $unknown = clone $base;
        $this->unknownPeriod($unknown);
        $unclassified = $unknown->count('rice_seed_distributions.id');
        $query = clone $base;
        if ($filters['season'] === 'unrecorded') {
            $this->unknownPeriod($query);
        } elseif ($filters['year'] || in_array($filters['season'], ['dry', 'wet'], true)) {
            $this->knownPeriod($query);
            if ($filters['year']) {
                $query->where('coverage_batch.planting_year', $filters['year']);
            }
            if ($filters['season'] !== 'all') {
                $query->where('coverage_batch.planting_season', $filters['season']);
            }
        }

        $totals = (clone $query)->groupBy('rice_seed_distributions.municipality_id')
            ->selectRaw('rice_seed_distributions.municipality_id as municipality_id, COUNT(*) as releases, COUNT(DISTINCT coverage_farmer.id) as beneficiaries')
            ->selectRaw('SUM(CASE WHEN coverage_farmer.id IS NULL THEN 1 ELSE 0 END) as unlinked')
            ->get()->keyBy('municipality_id');
        $units = array_keys(RiceSeedDistribution::QUANTITY_UNIT_LABELS);
        $quantityRows = (clone $query)->whereIn('quantity_unit', $units)->whereNotNull('kgs_received')->where('kgs_received', '>=', 0)
            ->groupBy('rice_seed_distributions.municipality_id', 'quantity_unit')
            ->selectRaw('rice_seed_distributions.municipality_id as municipality_id, quantity_unit as unit, SUM(kgs_received) as quantity')
            ->orderBy('quantity_unit')->get()->groupBy('municipality_id');
        $incomplete = (clone $query)->where(function (Builder $query) use ($units) {
            $query->whereNull('quantity_unit')->orWhereNotIn('quantity_unit', $units)->orWhereNull('kgs_received')->orWhere('kgs_received', '<', 0);
        })->count('rice_seed_distributions.id');
        $boundaryCounts = MunicipalityBoundary::query()->active()->whereIn('municipality_id', $ids)
            ->selectRaw('municipality_id, COUNT(*) as total')->groupBy('municipality_id')->pluck('total', 'municipality_id');

        $rows = $municipalities->map(function (Municipality $municipality) use ($totals, $quantityRows, $boundaryCounts) {
            $total = $totals->get($municipality->id);
            $boundaryCount = (int) ($boundaryCounts[$municipality->id] ?? 0);

            return [
                'id' => $municipality->id, 'name' => $municipality->name,
                'releases' => (int) ($total?->releases ?? 0),
                'beneficiaries' => (int) ($total?->beneficiaries ?? 0),
                'unlinked' => (int) ($total?->unlinked ?? 0),
                'boundary' => $boundaryCount === 1 ? 'available' : ($boundaryCount > 1 ? 'ambiguous' : 'missing'),
                'quantities' => collect($quantityRows->get($municipality->id, []))->map(fn ($row) => [
                    'unit' => RiceSeedDistribution::QUANTITY_UNIT_LABELS[$row->unit], 'quantity' => round((float) $row->quantity, 2),
                ])->all(),
            ];
        });

        return [
            'rows' => $rows->values()->all(),
            'summary' => [
                'releases' => (int) $rows->sum('releases'),
                'beneficiaries' => (int) $rows->sum('beneficiaries'),
                'municipalities_with_releases' => $rows->where('releases', '>', 0)->count(),
                'unlinked' => (int) $rows->sum('unlinked'),
                'unclassified' => $unclassified,
                'incomplete_quantities' => $incomplete,
                'without_boundary' => $rows->where('boundary', '!=', 'available')->count(),
            ],
            'references' => $references->take(200)->all(),
            'references_truncated' => $references->count() > 200,
        ];
    }

    /** @return array<string, mixed> */
    public function boundaries(User $user, array $requestedIds): array
    {
        $ids = $this->access->scopeMunicipalities(Municipality::query()->active(), $user)
            ->whereHas('supervisingProvince', fn (Builder $query) => $query->active())
            ->whereIn('id', $requestedIds)->pluck('id')->all();
        // An inconsistent set of active boundaries is excluded rather than chosen arbitrarily.
        $unique = MunicipalityBoundary::query()->active()->whereIn('municipality_id', $ids)
            ->groupBy('municipality_id')->havingRaw('COUNT(*) = 1')->select('municipality_id');
        $features = [];
        $omitted = [];
        foreach (MunicipalityBoundary::query()->active()->whereIn('municipality_id', $unique)
            ->get(['municipality_id', 'geojson', 'vertex_count']) as $boundary) {
            $geometry = $boundary->geojson;
            if (! is_array($geometry) || ! in_array($geometry['type'] ?? '', ['Polygon', 'MultiPolygon'], true)
                || ($boundary->vertex_count ?? PHP_INT_MAX) > 10000 || strlen(json_encode($geometry)) > 1500000) {
                $omitted[] = $boundary->municipality_id;

                continue;
            }
            $features[] = ['type' => 'Feature', 'id' => $boundary->municipality_id,
                'properties' => ['municipality_id' => $boundary->municipality_id], 'geometry' => $geometry];
        }

        return ['type' => 'FeatureCollection', 'features' => $features, 'omitted_ids' => $omitted];
    }
}
