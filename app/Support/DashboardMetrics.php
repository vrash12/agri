<?php

namespace App\Support;

use App\Models\AgriculturalMachinery;
use App\Models\AntiRabiesVaccination;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Scoped aggregates behind the dashboard charts.
 *
 * These live outside the controller because the measurement rules matter more than
 * the HTTP plumbing and need to be read, tested and argued about on their own. Each
 * method aggregates in the database over a query that `MunicipalityAccess` has
 * already scoped, so a municipal account sees its own municipality and a provincial
 * account its province.
 *
 * Rules these methods exist to enforce, from the office's reporting feedback:
 *
 * - A release is what was handed out. It is never reported as production or yield.
 * - Release transactions and unique beneficiaries are different numbers and are
 *   always returned as separate series.
 * - Quantities are never summed across units. Nothing in this system converts
 *   between kilograms, pieces and sacks, so each unit is reported on its own row and
 *   `quantityByUnit()` is rendered as figures rather than as one bar chart.
 * - Mapping coverage counts farmers. The number of unmapped parcels is unknowable
 *   without a parcel inventory, so it is never produced.
 * - Machinery condition and availability are separate dimensions.
 * - No composite municipality score, index or ranking is produced.
 * - A row whose grouping value is missing is reported under `not_recorded` or an
 *   explicitly labelled missing-classification bucket, never silently dropped. A
 *   row with no municipality at all: it belongs to no province either, so only an
 *   account that sees everything is told about it. See `unownedCount()`.
 *
 * Every method returns the same shape:
 *
 *     [
 *       'title'        => string,
 *       'labels'       => string[],
 *       'series'       => [ ['name','unit','decimals','values'], ... ],
 *       'not_recorded' => null | ['label' => string, 'count' => int],
 *       // Optional: distinguish a recorded zero from no complete records.
 *       'has_data'     => bool,
 *       // Optional: records whose reporting year cannot be assigned.
 *       'undated'      => null | ['label' => string, 'count' => int],
 *     ]
 *
 * Every series' `values` array is the same length as `labels`.
 *
 * Aggregates are read with `selectRaw(... as alias)` and `get()` rather than
 * `pluck(DB::raw(...))`. Laravel 9's `Builder::stripTableForPluck()` splits a raw
 * expression on the last dot, so `pluck(DB::raw('COUNT(farmers.id)'))` looks for a
 * result property named `id)` and dies with an undefined-property error. The alias
 * form is also the idiom already used in `DashboardController`.
 */
class DashboardMetrics
{
    /**
     * Visible municipality ids per user id; null means every municipality.
     *
     * @var array<int, array<int, int>|null>
     */
    private array $scopeCache = [];

    public function __construct(private MunicipalityAccess $municipalityAccess)
    {
    }

    /**
     * Registered farmers per municipality.
     *
     * @return array<string, mixed>
     */
    public function farmersByMunicipality(User $user): array
    {
        $rows = $this->scoped(Farmer::query(), $user)
            ->join('municipalities', 'municipalities.id', '=', 'farmers.municipality_id')
            ->groupBy('municipalities.id', 'municipalities.name')
            ->orderBy('municipalities.name')
            ->selectRaw('municipalities.name as label, COUNT(farmers.id) as total')
            ->get();

        return $this->metric(
            'Registered farmers by municipality',
            $rows->pluck('label')->all(),
            [$this->series('Registered farmers', 'farmers', $this->ints($rows, 'total'))],
            $this->unownedCount($user, Farmer::query(), 'farmers.municipality_id', 'Farmers with no municipality recorded')
        );
    }

    /**
     * Releases and unique beneficiaries per assistance category.
     *
     * Only categories that actually appear are listed. The vocabulary has eleven
     * entries and most municipalities use a handful, so listing the rest as zeros
     * would bury the real figures.
     *
     * @return array<string, mixed>
     */
    public function assistanceByCategory(User $user): array
    {
        $rows = $this->scoped(RiceSeedDistribution::query(), $user)
            ->whereNotNull('input_category')
            ->where('input_category', '!=', '')
            ->groupBy('input_category')
            ->selectRaw('input_category as grouping_key, COUNT(*) as releases')
            // A release with no linked farmer is a transaction, not a beneficiary.
            ->selectRaw('COUNT(DISTINCT farmer_id) as beneficiaries')
            ->get();

        $ordered = $this->orderByVocabulary($rows, RiceSeedDistribution::INPUT_CATEGORY_LABELS);

        return $this->metric(
            'Assistance by category',
            $ordered->pluck('label')->all(),
            [
                $this->series('Releases', 'releases', $this->ints($ordered, 'releases')),
                $this->series('Unique beneficiaries', 'farmers', $this->ints($ordered, 'beneficiaries')),
            ],
            $this->missing(
                $this->scoped(RiceSeedDistribution::query(), $user)
                    ->where(fn (Builder $q) => $q->whereNull('input_category')->orWhere('input_category', ''))
                    ->count(),
                'Releases with no category recorded'
            )
        );
    }

    /**
     * Releases and unique beneficiaries per municipality.
     *
     * @return array<string, mixed>
     */
    public function assistanceByMunicipality(User $user): array
    {
        $rows = $this->scoped(RiceSeedDistribution::query(), $user)
            ->join('municipalities', 'municipalities.id', '=', 'rice_seed_distributions.municipality_id')
            ->groupBy('municipalities.id', 'municipalities.name')
            ->orderBy('municipalities.name')
            ->selectRaw('municipalities.name as label, COUNT(*) as releases')
            ->selectRaw('COUNT(DISTINCT rice_seed_distributions.farmer_id) as beneficiaries')
            ->get();

        return $this->metric(
            'Assistance by municipality',
            $rows->pluck('label')->all(),
            [
                $this->series('Releases', 'releases', $this->ints($rows, 'releases')),
                $this->series('Unique beneficiaries', 'farmers', $this->ints($rows, 'beneficiaries')),
            ],
            $this->unownedCount($user, RiceSeedDistribution::query(), 'rice_seed_distributions.municipality_id', 'Releases with no municipality recorded')
        );
    }

    /**
     * Quantity released, one row per unit.
     *
     * Nothing in this system converts between units, so a single "total released"
     * across kilograms, pieces and sacks would be a meaningless number. The rows are
     * deliberately not comparable with one another, which is why the dashboard shows
     * this as a table of figures and not as a bar chart on a shared axis.
     *
     * @return array<string, mixed>
     */
    public function quantityByUnit(User $user): array
    {
        $rows = $this->scoped(RiceSeedDistribution::query(), $user)
            ->whereNotNull('quantity_unit')
            ->where('quantity_unit', '!=', '')
            ->groupBy('quantity_unit')
            ->selectRaw('quantity_unit as grouping_key, COALESCE(SUM(kgs_received), 0) as quantity')
            ->get();

        $ordered = $this->orderByVocabulary($rows, RiceSeedDistribution::QUANTITY_UNIT_LABELS);

        return $this->metric(
            'Quantity released by unit',
            $ordered->pluck('label')->all(),
            [$this->series('Quantity released', 'in the unit named on each row', $this->floats($ordered, 'quantity'), 2)],
            $this->missing(
                $this->scoped(RiceSeedDistribution::query(), $user)
                    ->where(fn (Builder $q) => $q->whereNull('quantity_unit')->orWhere('quantity_unit', ''))
                    ->count(),
                'Releases with no unit recorded'
            )
        );
    }

    /**
     * Mapped against unmapped farmers.
     *
     * Deliberately farmers, not parcels. The system stores the parcels that have
     * been drawn; it has no inventory of the parcels that exist in the field, so an
     * "unmapped parcels" figure could only be invented.
     *
     * @return array<string, mixed>
     */
    public function mappingCoverage(User $user): array
    {
        $total = $this->scoped(Farmer::query(), $user)->count();
        $mapped = $this->scoped(Farmer::query(), $user)
            ->whereIn('farmers.id', FarmPlot::query()->select('farm_plots.farmer_id')->whereNotNull('farm_plots.farmer_id'))
            ->count();

        return $this->metric(
            'Farm mapping coverage',
            ['Farmers with a mapped parcel', 'Farmers without a mapped parcel'],
            [$this->series('Farmers', 'farmers', [$mapped, max($total - $mapped, 0)])]
        );
    }

    /**
     * Animal-health services and the animals they covered.
     *
     * The two are separate measures: a single service record can cover many animals.
     * Every declared service type is listed, including those with no records, because
     * "no deworming was recorded this year" is a true and useful statement and it
     * keeps the axis stable between municipalities.
     *
     * @return array<string, mixed>
     */
    public function animalHealthByServiceType(User $user): array
    {
        $rows = $this->scoped(AntiRabiesVaccination::query(), $user)
            ->whereNotNull('service_type')
            ->where('service_type', '!=', '')
            ->groupBy('service_type')
            ->selectRaw('service_type as grouping_key, COUNT(*) as services')
            ->selectRaw('COALESCE(SUM(animal_count), 0) as animals')
            ->get();

        $ordered = $this->fillVocabulary($rows, AntiRabiesVaccination::SERVICE_TYPE_LABELS, ['services', 'animals']);

        return $this->metric(
            'Animal-health services by type',
            $ordered->pluck('label')->all(),
            [
                $this->series('Services recorded', 'services', $this->ints($ordered, 'services')),
                $this->series('Animals covered', 'animals', $this->ints($ordered, 'animals')),
            ],
            $this->missing(
                $this->scoped(AntiRabiesVaccination::query(), $user)
                    ->where(fn (Builder $q) => $q->whereNull('service_type')->orWhere('service_type', ''))
                    ->count(),
                'Services with no service type recorded'
            )
        );
    }

    /** @return array<string, mixed> */
    public function animalHealthByMonth(User $user, int $year): array
    {
        $base = $this->inYear($this->scoped(AntiRabiesVaccination::query(), $user), 'vaccination_date', $year);
        $month = $this->datePartExpression($base, 'month', 'vaccination_date');
        $rows = (clone $base)->whereNotNull('service_type')->where('service_type', '!=', '')
            ->selectRaw($month.' as month_number, service_type, COUNT(*) as total')
            ->groupByRaw($month)->groupBy('service_type')->get();
        $types = AntiRabiesVaccination::SERVICE_TYPE_LABELS;
        foreach ($rows->pluck('service_type')->unique()->sort() as $type) {
            $types[$type] ??= $type;
        }

        $series = [];
        foreach ($types as $type => $label) {
            $totals = $rows->where('service_type', $type)->keyBy('month_number');
            $series[] = $this->series($label, 'services', array_map(
                fn ($monthNumber) => (int) ($totals->get($monthNumber)->total ?? 0),
                range(1, 12)
            ));
        }

        return $this->metric(
            'Animal-health services by month · '.$year,
            ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            $series,
            $this->missing(
                (clone $base)->where(fn (Builder $q) => $q->whereNull('service_type')->orWhere('service_type', ''))->count(),
                'Services in '.$year.' with no service type recorded'
            )
        ) + [
            'has_data' => $rows->isNotEmpty(),
            'undated' => $this->missing($this->scoped(AntiRabiesVaccination::query(), $user)
                ->whereNull('vaccination_date')->count(), 'Services with no service date recorded'),
        ];
    }

    /** @return array<int, int> */
    public function reportingYears(User $user): array
    {
        $years = $this->scoped(HarvestRecord::query(), $user)
            ->whereBetween('harvest_year', [1900, 2100])->distinct()->pluck('harvest_year');

        foreach ([
            [AntiRabiesVaccination::query(), 'vaccination_date'],
            [RiceSeedDistribution::query(), 'date_received'],
        ] as [$query, $column]) {
            $query = $this->scoped($query, $user);
            $expression = $this->datePartExpression($query, 'year', $column);
            $rows = $query->where($column, '>=', '1900-01-01')->where($column, '<', '2101-01-01')
                ->selectRaw($expression.' as report_year')->distinct()->get();
            $years = $years->concat($rows->pluck('report_year'));
        }

        return $years->push((int) now()->year)->map(fn ($year) => (int) $year)
            ->unique()->sortDesc()->values()->all();
    }

    /** @return array<string, mixed> */
    public function fingerlingsByMunicipality(User $user, int $year): array
    {
        $municipalities = $this->activeMunicipalities($user);
        $base = $this->inYear($this->scoped(RiceSeedDistribution::query(), $user), 'date_received', $year)
            ->whereIn('municipality_id', $municipalities->pluck('id'))
            ->where('input_category', 'fish_fingerlings');
        // Legacy quantity lives in kgs_received even for pieces; its unit decides
        // whether the amount can honestly be counted as fingerlings.
        $rows = (clone $base)->groupBy('municipality_id')
            ->selectRaw('municipality_id, COUNT(*) as releases')
            ->selectRaw("SUM(CASE WHEN quantity_unit = 'piece' AND kgs_received IS NOT NULL THEN 1 ELSE 0 END) as complete_releases")
            ->selectRaw("SUM(CASE WHEN quantity_unit = 'piece' AND kgs_received IS NOT NULL THEN kgs_received ELSE 0 END) as total")
            ->get()->keyBy('municipality_id');
        $values = $municipalities->map(function ($municipality) use ($rows) {
            $row = $rows->get($municipality->id);

            return $row && (int) $row->complete_releases === 0 ? null : (float) ($row->total ?? 0);
        })->all();
        $missing = (int) $rows->sum(fn ($row) => (int) $row->releases - (int) $row->complete_releases);

        return $this->metric(
            'Fingerlings released by municipality · '.$year,
            $municipalities->pluck('name')->all(),
            [$this->series('Fingerlings released', 'pieces', $values, 2)],
            $this->missing($missing, 'Fingerling releases in '.$year.' with a missing quantity or a unit other than pieces')
        ) + [
            'has_data' => $rows->sum('complete_releases') > 0,
            'undated' => $this->missing($this->scoped(RiceSeedDistribution::query(), $user)
                ->whereIn('municipality_id', $municipalities->pluck('id'))
                ->where('input_category', 'fish_fingerlings')->whereNull('date_received')->count(),
                'Fingerling releases with no release date recorded'),
        ];
    }

    /**
     * Fisheries assistance by category.
     *
     * Both series are counts, so they share an axis honestly. The quantity of
     * fingerlings issued is a piece count and belongs with the other units in
     * `quantityByUnit()`, not on this chart.
     *
     * @return array<string, mixed>
     */
    public function fisheriesAssistance(User $user): array
    {
        $rows = $this->scoped(RiceSeedDistribution::query(), $user)
            ->whereIn('input_category', RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES)
            ->groupBy('input_category')
            ->selectRaw('input_category as grouping_key, COUNT(*) as releases')
            ->selectRaw('COUNT(DISTINCT farmer_id) as beneficiaries')
            ->get();

        $ordered = $this->orderByVocabulary($rows, RiceSeedDistribution::INPUT_CATEGORY_LABELS);

        return $this->metric('Fisheries assistance', $ordered->pluck('label')->all(), [
            $this->series('Releases', 'releases', $this->ints($ordered, 'releases')),
            $this->series('Unique beneficiaries', 'farmers', $this->ints($ordered, 'beneficiaries')),
        ]);
    }

    /**
     * Machinery by operating condition.
     *
     * Condition is what state a unit is in. Availability, below, is whether it can be
     * used right now. A unit can be in excellent condition and unavailable because it
     * is already in use, so the two are never merged.
     *
     * @return array<string, mixed>
     */
    public function machineryByCondition(User $user): array
    {
        return $this->machineryBreakdown(
            $user,
            'condition_status',
            AgriculturalMachinery::CONDITIONS,
            'Machinery by operating condition',
            'Units with no operating condition recorded'
        );
    }

    /**
     * Machinery by current availability.
     *
     * @return array<string, mixed>
     */
    public function machineryByAvailability(User $user): array
    {
        return $this->machineryBreakdown(
            $user,
            'availability_status',
            AgriculturalMachinery::AVAILABILITY_STATUSES,
            'Machinery by current availability',
            'Units with no availability recorded'
        );
    }

    /** @return array<string, mixed> */
    public function machineryConditionByType(User $user): array
    {
        return $this->machineryByType($user, 'condition_status', AgriculturalMachinery::CONDITIONS,
            'Machinery condition by equipment type', 'Condition not recorded');
    }

    /** @return array<string, mixed> */
    public function machineryAvailabilityByType(User $user): array
    {
        return $this->machineryByType($user, 'availability_status', AgriculturalMachinery::AVAILABILITY_STATUSES,
            'Machinery availability by equipment type', 'Availability not recorded');
    }

    /**
     * Missing and unrecognized legacy values remain visible as labelled buckets.
     *
     * @param  array<string, string>  $statuses
     * @return array<string, mixed>
     */
    private function machineryByType(User $user, string $column, array $statuses, string $title, string $missingStatus): array
    {
        $rows = $this->scoped(AgriculturalMachinery::query(), $user)
            ->selectRaw('category, '.$column.' as status, COUNT(*) as total')
            ->groupBy('category', $column)->get();
        $categories = [];
        foreach (AgriculturalMachinery::CATEGORIES as $key => $label) {
            if ($rows->contains('category', $key)) {
                $categories[$key] = $label;
            }
        }
        foreach ($rows as $row) {
            $category = (string) $row->category;
            $status = (string) $row->status;
            $categories[$category] ??= $category !== '' ? $category : 'Equipment type not recorded';
            $statuses[$status] ??= $status !== '' ? $status : $missingStatus;
        }
        $totals = [];
        foreach ($rows as $row) {
            // NULL and empty legacy values have the same missing-value meaning.
            $category = (string) $row->category;
            $status = (string) $row->status;
            $totals[$category][$status] = ($totals[$category][$status] ?? 0) + (int) $row->total;
        }
        $series = [];
        foreach ($statuses as $status => $label) {
            $series[] = $this->series($label, 'units', array_map(
                fn ($category) => $totals[$category][$status] ?? 0,
                array_keys($categories)
            ));
        }

        return $this->metric($title, array_values($categories), $series) + ['has_data' => $rows->isNotEmpty()];
    }

    /**
     * Recorded harvest over time, one series per commodity.
     *
     * This is the graph the office asked for and the one the system could not answer,
     * because the only harvest figures it held were three fields on a rice seed
     * release. A release says what was handed out; it cannot say what a farmer who
     * planted their own seed produced, and it cannot speak for corn or fish at all.
     *
     * Two rules this shares with every other figure on the dashboard. Units are never
     * summed across each other, so a commodity recorded in sacks and in kilograms
     * appears as two series rather than one wrong number. And a period is stated, not
     * inferred: a harvest with no year recorded is counted under `not_recorded`
     * instead of being placed in a year somebody guessed for it.
     *
     * @return array<string, mixed>
     */
    public function productionByCommodity(User $user): array
    {
        $rows = $this->scoped(HarvestRecord::query(), $user)
            ->whereNotNull('harvest_year')
            ->whereNotNull('commodity')
            ->where('commodity', '!=', '')
            ->whereNotNull('quantity')
            ->groupBy('harvest_year', 'commodity', 'quantity_unit')
            ->orderBy('harvest_year')
            ->selectRaw('harvest_year, commodity, quantity_unit, COALESCE(SUM(quantity), 0) as total')
            ->get();

        $years = $rows->pluck('harvest_year')->unique()->sort()->values();
        $labels = $years->map(fn ($year) => (string) $year)->all();

        // One series per commodity-and-unit pair. Two units for one commodity are two
        // different measurements, and putting them on one line would invent a total.
        $series = [];
        foreach ($rows->groupBy(fn ($row) => $row->commodity.'|'.($row->quantity_unit ?: '')) as $key => $group) {
            [$commodity, $unit] = explode('|', (string) $key);
            $byYear = $group->keyBy(fn ($row) => (int) $row->harvest_year);

            $series[] = $this->series(
                HarvestRecord::COMMODITY_LABELS[$commodity] ?? $commodity,
                HarvestRecord::QUANTITY_UNIT_LABELS[$unit] ?? ($unit ?: 'unit not recorded'),
                $years->map(fn ($year) => $byYear->has((int) $year) ? (float) $byYear[(int) $year]->total : null)->all(),
                3
            );
        }

        return $this->metric(
            'Recorded production by commodity',
            $labels,
            $series,
            $this->missing(
                $this->scoped(HarvestRecord::query(), $user)
                    ->where(fn (Builder $q) => $q->whereNull('harvest_year')->orWhereNull('quantity'))
                    ->count(),
                'Harvest records with no year or no quantity recorded'
            )
        ) + ['has_data' => $rows->isNotEmpty()];
    }

    /**
     * Per-indicator municipality comparison.
     *
     * Each indicator stands on its own series and the dashboard shows one at a time.
     * No composite score, index or ranking is produced: the office has not defined how
     * these indicators would be weighed against one another, so any single
     * "performance" number would be invented here rather than measured.
     *
     * Returns null for an account that cannot see more than one municipality, because
     * a one-row comparison is not a comparison.
     *
     * Only active municipalities are listed, which is the same set
     * `DashboardController::buildMunicipalityOverview()` puts in the table beneath, so
     * the chart and that table can never disagree.
     *
     * @return array<string, mixed>|null
     */
    public function municipalityComparison(User $user, ?int $reportYear = null): ?array
    {
        $municipalities = $this->activeMunicipalities($user);

        if ($municipalities->count() < 2) {
            return null;
        }

        $ids = $municipalities->pluck('id');

        // None of these models uses soft deletes or a global scope, so filtering by
        // the already-scoped municipality ids is equivalent to MunicipalityAccess.
        $farmers = $this->keyedTotals(
            Farmer::query()->whereIn('municipality_id', $ids),
            'COUNT(*) as total'
        );
        $mapped = $this->keyedTotals(
            Farmer::query()->whereIn('municipality_id', $ids)
                ->whereIn('id', FarmPlot::query()->select('farm_plots.farmer_id')->whereNotNull('farm_plots.farmer_id')),
            'COUNT(*) as total'
        );
        $releases = $this->keyedTotals(
            RiceSeedDistribution::query()->whereIn('municipality_id', $ids),
            'COUNT(*) as total'
        );
        $beneficiaries = $this->keyedTotals(
            RiceSeedDistribution::query()->whereIn('municipality_id', $ids),
            'COUNT(DISTINCT farmer_id) as total'
        );

        $columns = ['farmers' => [], 'mapped' => [], 'releases' => [], 'beneficiaries' => []];
        foreach ($municipalities as $municipality) {
            $columns['farmers'][] = (int) ($farmers[$municipality->id] ?? 0);
            $columns['mapped'][] = (int) ($mapped[$municipality->id] ?? 0);
            $columns['releases'][] = (int) ($releases[$municipality->id] ?? 0);
            $columns['beneficiaries'][] = (int) ($beneficiaries[$municipality->id] ?? 0);
        }

        $series = [
            $this->series('Registered farmers', 'farmers', $columns['farmers']),
            $this->series('Farmers with a mapped parcel', 'farmers', $columns['mapped']),
            $this->series('Assistance releases', 'releases', $columns['releases']),
            $this->series('Unique beneficiaries', 'farmers', $columns['beneficiaries']),
        ];
        $reportYear ??= (int) now()->year;
        $harvests = HarvestRecord::query()->whereIn('municipality_id', $ids)->where('harvest_year', $reportYear);
        $production = (clone $harvests)->whereNotNull('commodity')->where('commodity', '!=', '')
            ->whereNotNull('quantity')->whereIn('quantity_unit', array_keys(HarvestRecord::QUANTITY_UNIT_LABELS))
            ->groupBy('municipality_id', 'commodity', 'quantity_unit')->orderBy('commodity')->orderBy('quantity_unit')
            ->selectRaw('municipality_id, commodity, quantity_unit, SUM(quantity) as total')->get();
        foreach ($production->groupBy(fn ($row) => $row->commodity.'|'.$row->quantity_unit) as $group) {
            $first = $group->first();
            $totals = $group->keyBy('municipality_id');
            $series[] = $this->series(
                'Production '.$reportYear.' · '.(HarvestRecord::COMMODITY_LABELS[$first->commodity] ?? $first->commodity),
                HarvestRecord::QUANTITY_UNIT_LABELS[$first->quantity_unit],
                $municipalities->map(fn ($municipality) => $totals->has($municipality->id)
                    ? (float) $totals->get($municipality->id)->total : null)->all(),
                3
            );
        }
        $missing = (clone $harvests)->where(fn (Builder $q) => $q
            ->whereNull('commodity')->orWhere('commodity', '')->orWhereNull('quantity')
            ->orWhereNull('quantity_unit')->orWhereNotIn('quantity_unit', array_keys(HarvestRecord::QUANTITY_UNIT_LABELS)))->count();

        return $this->metric('Municipality comparison', $municipalities->pluck('name')->all(), $series,
            $this->missing($missing, 'Harvest records in '.$reportYear.' with a missing commodity, quantity, or recognized unit')) + [
                'undated' => $this->missing(HarvestRecord::query()->whereIn('municipality_id', $ids)
                    ->whereNull('harvest_year')->count(), 'Harvest records with no harvest year recorded'),
            ];
    }

    /** @return Collection<int, Municipality> */
    private function activeMunicipalities(User $user): Collection
    {
        return $this->municipalityAccess->scopeMunicipalities(Municipality::query(), $user)
            ->active()->orderBy('name')->get(['municipalities.id', 'municipalities.name']);
    }

    private function inYear(Builder $query, string $column, int $year): Builder
    {
        return $query->where($column, '>=', $year.'-01-01')->where($column, '<', ($year + 1).'-01-01');
    }

    private function datePartExpression(Builder $query, string $part, string $column): string
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);
        if ($query->getConnection()->getDriverName() === 'sqlite') {
            $format = $part === 'month' ? '%m' : '%Y';

            return "CAST(strftime('".$format."', ".$wrapped.') AS INTEGER)';
        }

        return ($part === 'month' ? 'MONTH' : 'YEAR').'('.$wrapped.')';
    }

    /**
     * Group a municipality-scoped query and return totals keyed by municipality id.
     *
     * @return array<int, int>
     */
    private function keyedTotals(Builder $query, string $aggregate): array
    {
        return $query->groupBy('municipality_id')
            ->selectRaw('municipality_id, '.$aggregate)
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->municipality_id => (int) $row->total])
            ->all();
    }

    /**
     * @param  array<string, string>  $vocabulary
     * @return array<string, mixed>
     */
    private function machineryBreakdown(
        User $user,
        string $column,
        array $vocabulary,
        string $title,
        string $missingLabel
    ): array {
        $rows = $this->scoped(AgriculturalMachinery::query(), $user)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->selectRaw($column.' as grouping_key, COUNT(*) as total')
            ->get();

        $ordered = $this->fillVocabulary($rows, $vocabulary, ['total']);

        return $this->metric(
            $title,
            $ordered->pluck('label')->all(),
            [$this->series('Units', 'units', $this->ints($ordered, 'total'))],
            $this->missing(
                $this->scoped(AgriculturalMachinery::query(), $user)
                    ->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, ''))
                    ->count(),
                $missingLabel
            )
        );
    }

    /**
     * Order rows by a label vocabulary, keeping only the keys that appear.
     *
     * Rows whose key is not in the vocabulary are kept and labelled with the raw key
     * rather than dropped, so a value written straight into the database still shows
     * up instead of quietly vanishing from the totals.
     *
     * @param  array<string, string>  $vocabulary
     */
    private function orderByVocabulary(Collection $rows, array $vocabulary): Collection
    {
        $byKey = $rows->keyBy(fn ($row) => (string) $row->grouping_key);
        $ordered = collect();

        foreach (array_keys($vocabulary) as $key) {
            if ($byKey->has($key)) {
                $ordered->push($this->labelled($byKey->get($key), $vocabulary[$key]));
            }
        }
        foreach ($byKey as $key => $row) {
            if (! array_key_exists($key, $vocabulary)) {
                $ordered->push($this->labelled($row, (string) $key));
            }
        }

        return $ordered;
    }

    /**
     * Order rows by a label vocabulary, listing every declared key including the ones
     * with no records.
     *
     * Used for the small closed vocabularies — machinery condition and availability,
     * animal-health service type — where a zero is a true statement about the office
     * rather than a gap in the data, and where a stable axis lets two municipalities
     * be read against each other.
     *
     * @param  array<string, string>  $vocabulary
     * @param  array<int, string>  $totals
     */
    private function fillVocabulary(Collection $rows, array $vocabulary, array $totals): Collection
    {
        $byKey = $rows->keyBy(fn ($row) => (string) $row->grouping_key);
        $ordered = collect();

        foreach ($vocabulary as $key => $label) {
            $row = $byKey->get($key);
            if ($row) {
                $ordered->push($this->labelled($row, $label));

                continue;
            }
            $blank = ['label' => $label];
            foreach ($totals as $total) {
                $blank[$total] = 0;
            }
            $ordered->push((object) $blank);
        }
        foreach ($byKey as $key => $row) {
            if (! array_key_exists($key, $vocabulary)) {
                $ordered->push($this->labelled($row, (string) $key));
            }
        }

        return $ordered;
    }

    private function labelled(object $row, string $label): object
    {
        $row->label = $label;

        return $row;
    }

    /**
     * @return array<int, int>
     */
    private function ints(Collection $rows, string $key): array
    {
        return $rows->map(fn ($row) => (int) ($row->{$key} ?? 0))->values()->all();
    }

    /**
     * @return array<int, float>
     */
    private function floats(Collection $rows, string $key): array
    {
        return $rows->map(fn ($row) => (float) ($row->{$key} ?? 0))->values()->all();
    }

    /**
     * Apply the account's municipality scope to a query.
     *
     * This does what `MunicipalityAccess::scope()` does, but resolves the visible
     * municipality ids once per request instead of once per call. `scope()` asks
     * `hasUsableScope()` every time, and that runs its own existence query against
     * `provinces` or `municipalities`; building a dashboard means scoping roughly
     * twenty queries, so going through it directly cost about thirty round trips
     * that all returned the same answer.
     */
    /**
     * Rows that belong to no municipality at all.
     *
     * These cannot be counted through `scoped()`. That adds
     * `municipality_id IN (...)`, and `NULL IN (...)` is UNKNOWN rather than true, so
     * the combined predicate can never match and the figure was silently zero for
     * every account except the system owner.
     *
     * Reported only to an account whose view is unrestricted. A row with no
     * municipality belongs to no province either, so there is no honest figure to
     * give a provincial or municipal account: showing it to a provincial account
     * would assert an ownership the data does not support, and showing it to a
     * municipal one would disclose that records exist outside its scope. The system
     * owner already sees the same rows in the unassigned-records figure on the
     * municipality overview.
     *
     * Gated on the resolved scope rather than on the role, because a deactivated
     * system owner has no usable scope and must not be handed an unscoped count.
     *
     * @return array<string, mixed>|null
     */
    private function unownedCount(User $user, Builder $query, string $column, string $label): ?array
    {
        if ($this->visibleMunicipalityIds($user) !== null) {
            return null;
        }

        return $this->missing($query->whereNull($column)->count(), $label);
    }

    private function scoped(Builder $query, User $user): Builder
    {
        $ids = $this->visibleMunicipalityIds($user);

        // A system owner oversees every province, so no restriction is added — the
        // same case `MunicipalityAccess::scope()` short-circuits.
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($query->getModel()->qualifyColumn('municipality_id'), $ids);
    }

    /**
     * The municipality ids this account may read, or null when it may read them all.
     *
     * An account with no usable scope resolves to an empty list, which makes every
     * aggregate return nothing. That matches `MunicipalityAccess`, which fails closed
     * rather than open.
     *
     * @return array<int, int>|null
     */
    private function visibleMunicipalityIds(User $user): ?array
    {
        $key = (int) $user->getKey();

        if (! array_key_exists($key, $this->scopeCache)) {
            $this->scopeCache[$key] = match (true) {
                ! $user->hasUsableScope() => [],
                $user->isSystemOwner() => null,
                default => $this->municipalityAccess
                    ->scopeMunicipalities(Municipality::query(), $user)
                    ->pluck('municipalities.id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            };
        }

        return $this->scopeCache[$key];
    }

    /**
     * @param  array<int, int|float|null>  $values
     * @return array<string, mixed>
     */
    private function series(string $name, string $unit, array $values, int $decimals = 0): array
    {
        return [
            'name' => $name,
            'unit' => $unit,
            'decimals' => $decimals,
            'values' => array_values($values),
        ];
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<int, array<string, mixed>>  $series
     * @param  array<string, mixed>|null  $notRecorded
     * @return array<string, mixed>
     */
    private function metric(string $title, array $labels, array $series, ?array $notRecorded = null): array
    {
        return [
            'title' => $title,
            'labels' => array_values($labels),
            'series' => array_values($series),
            'not_recorded' => $notRecorded,
        ];
    }

    /**
     * Rows excluded because the value being grouped on is absent.
     *
     * Reported as its own figure so the reader can see that something is missing.
     * Returning zero here would claim the records do not exist.
     *
     * @return array<string, mixed>|null
     */
    private function missing(int $count, string $label): ?array
    {
        return $count > 0 ? ['label' => $label, 'count' => $count] : null;
    }
}
