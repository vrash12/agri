<?php

namespace App\Modules\Reporting\Application;

use App\Models\AgriculturalMachinery;
use App\Models\AntiRabiesVaccination;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Modules\Farmers\Application\FarmerDataQuality;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregate figures for a period across a fixed set of municipalities.
 *
 * The caller has already decided which municipalities the reader may see; this
 * class only measures them. Every query is grouped by municipality, so the cost
 * stays at about ten queries however many offices a report covers, and a report
 * for a province is exactly the sum of its municipalities' figures.
 *
 * The same measurement rules as the dashboard apply: releases are never called
 * production, beneficiaries require a valid same-municipality farmer link,
 * quantities are never added across units, and missing dates are reported as
 * their own counts rather than dropped. The output holds aggregates only — no
 * names, identifiers, addresses or record text.
 */
final class PeriodReportBuilder
{
    /**
     * @param  array<int, int>  $municipalityIds
     * @return array<string, mixed>
     */
    public function build(array $municipalityIds, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $ids = array_values(array_unique(array_map('intval', $municipalityIds)));
        $names = Municipality::query()->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->all();
        $rows = $this->perMunicipality(array_keys($names), $start, $end);

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'municipality_count' => count($names),
            'totals' => $this->sum($rows),
            'breakdown' => count($names) > 1 ? array_map(
                fn (int $id): array => ['municipality' => $names[$id]] + $this->headline($rows[$id]),
                array_keys($names)
            ) : [],
            'labels' => [
                'categories' => RiceSeedDistribution::INPUT_CATEGORY_LABELS,
                'release_units' => RiceSeedDistribution::QUANTITY_UNIT_LABELS,
                'commodities' => HarvestRecord::COMMODITY_LABELS,
                'harvest_units' => HarvestRecord::QUANTITY_UNIT_LABELS,
                'services' => AntiRabiesVaccination::SERVICE_TYPE_LABELS,
                'availability' => AgriculturalMachinery::AVAILABILITY_STATUSES,
            ],
        ];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function perMunicipality(array $ids, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = array_fill_keys($ids, $this->blank());
        if ($ids === []) {
            return $rows;
        }
        $from = $start->toDateString();
        // Exclusive upper bound, so a stored time on the last day is still inside.
        $until = $end->addDay()->toDateString();

        Farmer::query()->whereIn('municipality_id', $ids)->groupBy('municipality_id')
            ->selectRaw('municipality_id, COUNT(*) as farmers')
            // Registration timestamps are UTC; operational date columns below are local dates.
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as new_farmers', [
                $start->startOfDay()->utc()->toDateTimeString(),
                $end->addDay()->startOfDay()->utc()->toDateTimeString(),
            ])
            ->selectRaw(FarmerDataQuality::missingFfrsCountExpression().' as missing_ffrs')
            ->selectRaw(FarmerDataQuality::missingLocationCountExpression().' as missing_location')
            ->get()->each(function ($row) use (&$rows): void {
                $rows[(int) $row->municipality_id]['registry'] = [
                    'farmers' => (int) $row->farmers,
                    'new_farmers' => (int) $row->new_farmers,
                    'mapped_farmers' => 0,
                    'missing_ffrs' => (int) $row->missing_ffrs,
                    'missing_location' => (int) $row->missing_location,
                ];
            });
        Farmer::query()->whereIn('municipality_id', $ids)
            ->whereIn('id', FarmPlot::query()->select('farm_plots.farmer_id')->whereNotNull('farm_plots.farmer_id'))
            ->groupBy('municipality_id')->selectRaw('municipality_id, COUNT(*) as total')
            ->get()->each(function ($row) use (&$rows): void {
                $rows[(int) $row->municipality_id]['registry']['mapped_farmers'] = (int) $row->total;
            });

        $releases = RiceSeedDistribution::query()->whereIn('municipality_id', $ids)
            ->where('date_received', '>=', $from)->where('date_received', '<', $until);
        (clone $releases)->groupBy('municipality_id', 'input_category', 'quantity_unit')
            ->selectRaw('municipality_id, input_category, quantity_unit, COUNT(*) as releases, COALESCE(SUM(kgs_received), 0) as quantity')
            ->get()->each(function ($row) use (&$rows): void {
                $assistance = &$rows[(int) $row->municipality_id]['assistance'];
                $category = $row->input_category ?: 'not_recorded';
                $unit = $row->quantity_unit ?: 'not_recorded';
                $assistance['releases'] += (int) $row->releases;
                $assistance['categories'][$category]['releases'] = ($assistance['categories'][$category]['releases'] ?? 0) + (int) $row->releases;
                $assistance['categories'][$category]['quantities'][$unit] = round(($assistance['categories'][$category]['quantities'][$unit] ?? 0) + (float) $row->quantity, 3);
            });
        // A farmer belongs to one municipality, so per-municipality distinct counts
        // add up to a correct distinct count for any wider scope.
        $linked = (clone $releases)->whereHas('farmer', fn (Builder $query) => $query
            ->whereColumn('farmers.municipality_id', 'rice_seed_distributions.municipality_id'));
        (clone $linked)->groupBy('municipality_id')->selectRaw('municipality_id, COUNT(DISTINCT farmer_id) as total')
            ->get()->each(function ($row) use (&$rows): void {
                $rows[(int) $row->municipality_id]['assistance']['beneficiaries'] = (int) $row->total;
            });
        (clone $linked)->groupBy('municipality_id', 'input_category')
            ->selectRaw('municipality_id, input_category, COUNT(DISTINCT farmer_id) as total')
            ->get()->each(function ($row) use (&$rows): void {
                $category = $row->input_category ?: 'not_recorded';
                $rows[(int) $row->municipality_id]['assistance']['categories'][$category]['beneficiaries'] = (int) $row->total;
            });
        RiceSeedDistribution::query()->whereIn('municipality_id', $ids)->whereNull('date_received')
            ->groupBy('municipality_id')->selectRaw('municipality_id, COUNT(*) as total')
            ->get()->each(function ($row) use (&$rows): void {
                $rows[(int) $row->municipality_id]['assistance']['undated'] = (int) $row->total;
            });

        if (Schema::hasTable('harvest_records')) {
            HarvestRecord::query()->whereIn('municipality_id', $ids)
                ->where('date_harvested', '>=', $from)->where('date_harvested', '<', $until)
                ->groupBy('municipality_id', 'commodity', 'quantity_unit')
                ->selectRaw('municipality_id, commodity, quantity_unit, COUNT(*) as records, COALESCE(SUM(quantity), 0) as quantity')
                ->get()->each(function ($row) use (&$rows): void {
                    $harvests = &$rows[(int) $row->municipality_id]['harvests'];
                    $key = ($row->commodity ?: 'not_recorded').'|'.($row->quantity_unit ?: 'not_recorded');
                    $harvests['records'] += (int) $row->records;
                    $harvests['production'][$key]['records'] = ($harvests['production'][$key]['records'] ?? 0) + (int) $row->records;
                    $harvests['production'][$key]['quantity'] = round(($harvests['production'][$key]['quantity'] ?? 0) + (float) $row->quantity, 3);
                });
            // Harvests reported for the period's year(s) without a harvest date cannot
            // be placed inside a month or quarter, so they are counted, not guessed.
            HarvestRecord::query()->whereIn('municipality_id', $ids)->whereNull('date_harvested')
                ->whereBetween('harvest_year', [$start->year, $end->year])
                ->groupBy('municipality_id')->selectRaw('municipality_id, COUNT(*) as total')
                ->get()->each(function ($row) use (&$rows): void {
                    $rows[(int) $row->municipality_id]['harvests']['undated'] = (int) $row->total;
                });
        }

        AntiRabiesVaccination::query()->whereIn('municipality_id', $ids)
            ->where('vaccination_date', '>=', $from)->where('vaccination_date', '<', $until)
            ->groupBy('municipality_id', 'service_type')
            ->selectRaw('municipality_id, service_type, COUNT(*) as services, COALESCE(SUM(animal_count), 0) as animals')
            ->get()->each(function ($row) use (&$rows): void {
                $health = &$rows[(int) $row->municipality_id]['animal_health'];
                $type = $row->service_type ?: 'not_recorded';
                $health['services'] += (int) $row->services;
                $health['animals'] += (int) $row->animals;
                $health['types'][$type]['services'] = ($health['types'][$type]['services'] ?? 0) + (int) $row->services;
                $health['types'][$type]['animals'] = ($health['types'][$type]['animals'] ?? 0) + (int) $row->animals;
            });

        AgriculturalMachinery::query()->whereIn('municipality_id', $ids)
            ->groupBy('municipality_id', 'availability_status')
            ->selectRaw('municipality_id, availability_status, COUNT(*) as total')
            ->get()->each(function ($row) use (&$rows): void {
                $machinery = &$rows[(int) $row->municipality_id]['machinery'];
                $status = $row->availability_status ?: 'not_recorded';
                $machinery['units'] += (int) $row->total;
                $machinery['availability'][$status] = ($machinery['availability'][$status] ?? 0) + (int) $row->total;
            });

        return $rows;
    }

    /** @return array<string, mixed> */
    private function blank(): array
    {
        return [
            'registry' => ['farmers' => 0, 'new_farmers' => 0, 'mapped_farmers' => 0, 'missing_ffrs' => 0, 'missing_location' => 0],
            'assistance' => ['releases' => 0, 'beneficiaries' => 0, 'undated' => 0, 'categories' => []],
            'harvests' => ['records' => 0, 'undated' => 0, 'production' => []],
            'animal_health' => ['services' => 0, 'animals' => 0, 'types' => []],
            'machinery' => ['units' => 0, 'availability' => []],
        ];
    }

    /**
     * Adds per-municipality rows. Every figure is a count or a same-unit quantity,
     * so addition is valid; rates are derived afterwards from the summed parts.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function sum(array $rows): array
    {
        $total = $this->blank();
        foreach ($rows as $row) {
            $total = $this->add($total, $row);
        }
        $registry = $total['registry'];
        $total['registry']['mapping_coverage'] = $registry['farmers'] > 0
            ? round($registry['mapped_farmers'] / $registry['farmers'] * 100, 1) : null;
        $total['assistance']['reach'] = $registry['farmers'] > 0
            ? round($total['assistance']['beneficiaries'] / $registry['farmers'] * 100, 1) : null;

        return $total;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private function add(array $left, array $right): array
    {
        foreach ($right as $key => $value) {
            if (is_array($value)) {
                $left[$key] = $this->add($left[$key] ?? [], $value);
            } else {
                $left[$key] = is_float($value) || is_float($left[$key] ?? 0)
                    ? round((float) ($left[$key] ?? 0) + (float) $value, 3)
                    : (int) ($left[$key] ?? 0) + (int) $value;
            }
        }

        return $left;
    }

    /**
     * The side-by-side counts for one municipality. Counts only: no score or rank.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, int|float|null>
     */
    private function headline(array $row): array
    {
        $farmers = $row['registry']['farmers'];

        return [
            'farmers' => $farmers,
            'mapping_coverage' => $farmers > 0 ? round($row['registry']['mapped_farmers'] / $farmers * 100, 1) : null,
            'releases' => $row['assistance']['releases'],
            'beneficiaries' => $row['assistance']['beneficiaries'],
            'harvest_records' => $row['harvests']['records'],
            'animal_services' => $row['animal_health']['services'],
        ];
    }
}
