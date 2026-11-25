<?php

namespace App\Modules\Climate\Application;

use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\ParcelCropSeason;
use App\Models\ParcelFieldAssessment;
use App\Models\User;
use App\Modules\Land\Application\ParcelCropSeasons;
use App\Support\MunicipalityAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Plain-language parcel briefing composed locally from saved evidence.
 * It makes no provider request and never turns missing evidence into zero.
 */
final class ParcelBriefing
{
    /** Application display band for "about the same", in index units; not a validated stress threshold. */
    public const SATELLITE_STABLE_BAND = 0.05;

    public const SATELLITE_TRENDS = ['falling', 'stable', 'rising', 'unavailable'];

    public const RAINFALL = ['below_reference', 'little_rain_forecast', 'no_dry_signal', 'unavailable'];

    private const HARVEST_LIMIT = 3;

    public function __construct(
        private ParcelFieldAssessments $fields,
        private ClimateRiskDashboard $dashboard,
        private ClimateAutomaticMonitor $monitor,
        private ParcelSatelliteHistory $satellites,
        private ParcelStressMonitor $stressMonitor,
        private ParcelSuitability $suitability,
        private ParcelCropSeasons $seasons,
        private MunicipalityAccess $access,
    ) {}

    /** The same saved evidence the guidance page shows. Authorizes the parcel before reading relationships. */
    public function evidence(FarmPlot $plot, User $user, int $year, string $season): array
    {
        Gate::forUser($user)->authorize('view', $plot);
        $plot->loadMissing('farmer.municipality');
        abort_unless($plot->farmer, 404);
        $latest = $this->fields->latest($plot, $user, $year, $season);
        $assessment = $latest ? $this->fields->present($latest, $plot) : null;
        $climate = $this->monitor->summarize($this->dashboard->automatedEvidence($user, $plot->farmer->municipality));
        $satellite = $this->satellites->forPlot($plot);

        return ['latest' => $latest, 'assessment' => $assessment, 'climate' => $climate, 'satellite' => $satellite,
            'stress' => $this->stressMonitor->summarize($assessment, $climate, $satellite),
            'suitability' => $this->suitability->evaluate($assessment ?? [])];
    }

    public function compose(FarmPlot $plot, User $user, int $year, string $season, array $evidence): array
    {
        Gate::forUser($user)->authorize('view', $plot);
        $plot->loadMissing('farmer');
        $stress = $evidence['stress'];
        $assessment = $evidence['assessment'] ?? null;
        $seasonLabel = $year.' '.($season === 'wet' ? 'wet' : 'dry').' season';

        $crop = $this->cropItem($plot, $user, $year, $season, $seasonLabel);
        [$satelliteItem, $satelliteTrend] = $this->satelliteItem($stress['satellite'] ?? []);
        [$weatherItem, $rainfall] = $this->weatherItem($evidence['climate'] ?? []);
        $items = [$crop['item'], $this->visitItem($assessment, $stress), $satelliteItem, $weatherItem, $this->harvestItem($plot, $user)];

        $cropKey = $crop['crop'];
        if ($cropKey === 'not_recorded' && ($assessment['context_current'] ?? false) && ($stress['observed_on'] ?? null)) {
            $cropKey = ['rice' => 'rice', 'corn' => 'corn', 'mungbean' => 'legumes'][$assessment['crop'] ?? ''] ?? 'not_recorded';
        }

        return [
            'headline' => $stress['label'] ?? 'Add current field evidence',
            'context' => ParcelCropSeason::CROPS[$cropKey].' · '.ucfirst($seasonLabel),
            'items' => $items,
            'unavailable' => array_values(array_column(array_filter($items, fn (array $item) => $item['status'] === 'unavailable'), 'title')),
            'next_step' => $this->nextStep($stress, $evidence['suitability'] ?? []),
            // The only values the optional provider advice may use; all are fixed enumerations.
            'categories' => [
                'crop' => $cropKey, 'season' => $season === 'wet' ? 'wet' : 'dry',
                'growth_stage' => $stress['stage'] ?? 'unknown', 'condition' => $stress['condition'] ?? 'unknown',
                'water_supply' => $stress['water'] ?? 'unknown', 'satellite_trend' => $satelliteTrend,
                'rainfall' => $rainfall,
            ],
            'limits' => 'Composed in AgriLGU from saved records and the rules used on this page. No AI was used. Missing evidence is shown as unavailable, not as zero. Confirm conditions on the farm.',
        ];
    }

    private function cropItem(FarmPlot $plot, User $user, int $year, string $season, string $seasonLabel): array
    {
        $item = ['key' => 'crop', 'title' => 'Crop this season', 'status' => 'unavailable', 'scope' => 'Parcel crop record', 'lines' => []];
        try {
            $record = Schema::hasTable('parcel_crop_seasons') ? ParcelCropSeason::query()->where('farm_plot_id', $plot->id)
                ->where('crop_year', $year)->where('season', $season)
                ->where('municipality_id', (int) $plot->farmer->municipality_id)->first() : null;
        } catch (Throwable) {
            $record = null;
        }
        if (! $record || ! Gate::forUser($user)->allows('view', $record) || ! isset(ParcelCropSeason::CROPS[$record->crop])
            || $record->crop === 'not_recorded') {
            $item['text'] = 'No crop is recorded for the '.$seasonLabel.'. Record it from the parcel map.';

            return ['item' => $item, 'crop' => 'not_recorded'];
        }
        $rows = $this->seasons->modalRows($plot, $record);
        $item['status'] = 'available';
        $item['text'] = ParcelCropSeason::CROPS[$record->crop].' is recorded for the '.$seasonLabel.'.';
        $planted = $rows['planted_record'] ?? null;
        if ($planted && $planted['needs_review']) {
            $item['lines'][] = 'Saved planted sections need office review against the current parcel boundary.';
        } elseif ($planted && $planted['areas'] !== []) {
            $item['lines'][] = count($planted['areas']).' planted '.(count($planted['areas']) === 1 ? 'section' : 'sections').': '
                .implode(', ', array_map(fn (array $area) => $area['crop_label']
                    .(is_numeric($area['area_ha'] ?? null) ? ' '.number_format((float) $area['area_ha'], 2).' ha' : ' (line mark, no area)'), $planted['areas'])).'.';
        } else {
            $item['lines'][] = 'No planted sections are drawn. The mapped parcel size does not measure planted area.';
        }

        return ['item' => $item, 'crop' => $record->crop];
    }

    private function visitItem(?array $assessment, array $stress): array
    {
        $item = ['key' => 'visit', 'title' => 'Last field visit', 'status' => 'unavailable', 'scope' => 'Parcel field check', 'lines' => []];
        $followUp = $stress['follow_up'] ?? [];
        if (! $assessment) {
            $item['text'] = 'No field check is recorded for this season.';

            return $item;
        }
        $date = $this->date($assessment['observed_on'] ?? null);
        if (! ($assessment['context_current'] ?? false)) {
            $item['text'] = 'The last field check ('.$date.') was for an earlier parcel boundary or owner. Record a new check.';

            return $item;
        }
        if (! ($stress['observed_on'] ?? null)) {
            $item['text'] = 'The last field check ('.$date.') is older than '.config('agronomy.freshness_days.field', 14)
                .' days. Its crop stage, condition and water readings are treated as unavailable.';
        } else {
            $item['status'] = 'available';
            $item['text'] = 'Checked '.$date.': '.($stress['stage'] ? ParcelFieldAssessment::STAGES[$stress['stage']] : 'stage not confirmed')
                .'; '.($stress['condition'] ? ParcelFieldAssessment::CONDITIONS[$stress['condition']] : 'condition not checked')
                .'; '.($stress['water'] ? ParcelFieldAssessment::WATER[$stress['water']] : 'water supply not verified').'.';
        }
        if ($followUp['overdue'] ?? false) {
            $item['status'] = 'attention';
            $item['lines'][] = 'Follow-up inspection was due '.$this->date($followUp['due_on'] ?? null).'.';
        } elseif (($followUp['status'] ?? null) === 'completed') {
            $item['lines'][] = 'The last follow-up was completed.';
        } elseif ($followUp['due_on'] ?? null) {
            $item['lines'][] = 'Next inspection planned '.$this->date($followUp['due_on']).'.';
        }

        return $item;
    }

    /** @return array{0: array, 1: string} */
    private function satelliteItem(array $series): array
    {
        $item = ['key' => 'satellite', 'title' => 'Satellite trend', 'status' => 'unavailable', 'scope' => 'Saved parcel satellite checks', 'lines' => [],
            'text' => 'No usable saved satellite pictures for this crop period. No new satellite request is made by this summary.'];
        $byIndex = array_column($series, null, 'index');
        $trend = 'unavailable';
        foreach (['ndvi', 'evi', 'savi'] as $index) {
            $row = $byIndex[$index] ?? null;
            if (! $row || $row['status'] === 'unavailable') {
                continue;
            }
            $points = $row['observations'] ?? [];
            $last = $points ? $points[count($points) - 1]['date'] : null;
            $name = strtoupper($index);
            if ($row['status'] === 'comparable' && is_numeric($row['delta'])) {
                $delta = (float) $row['delta'];
                $trend = $delta <= -self::SATELLITE_STABLE_BAND ? 'falling' : ($delta >= self::SATELLITE_STABLE_BAND ? 'rising' : 'stable');
                $item['status'] = 'available';
                $item['text'] = $name.' greenness '.['falling' => 'fell', 'rising' => 'rose', 'stable' => 'stayed about the same'][$trend]
                    .' ('.($delta > 0 ? '+' : '').number_format($delta, 3).') up to '.$this->date($last).'.';
            } else {
                $item['text'] = match ($row['status']) {
                    'stale' => 'The latest usable '.$name.' picture ('.$this->date($last).') is older than the monitoring window.',
                    'single' => 'One usable '.$name.' picture ('.$this->date($last).'). More pictures are needed to describe a change.',
                    default => 'Saved '.$name.' pictures are not suitably spaced for a comparison.',
                };
            }
            break;
        }
        $ndmi = $byIndex['ndmi'] ?? null;
        if ($ndmi && $ndmi['status'] === 'comparable' && is_numeric($ndmi['delta'])) {
            $item['lines'][] = 'Plant moisture indicator (NDMI) changed by '.((float) $ndmi['delta'] > 0 ? '+' : '').number_format((float) $ndmi['delta'], 3).'.';
        }
        if ($item['status'] !== 'unavailable' || $trend !== 'unavailable') {
            $item['lines'][] = 'A change can reflect growth, harvest, clouds or other causes; it does not diagnose stress.';
        } elseif ($item['text'] !== '' && ! str_starts_with($item['text'], 'No usable')) {
            $item['status'] = 'limited';
        }

        return [$item, $trend];
    }

    /** @return array{0: array, 1: string} */
    private function weatherItem(array $climate): array
    {
        $item = ['key' => 'weather', 'title' => 'Municipality weather and rainfall', 'status' => 'unavailable',
            'scope' => 'Municipality-level, not measured at this parcel', 'lines' => []];
        $forecast = $climate['sources']['open_meteo'] ?? [];
        $chirps = $climate['sources']['chirps'] ?? [];
        $low = false;
        $below = false;
        $comparable = false;
        if (($forecast['usable'] ?? false) && is_numeric($forecast['reading']['value'] ?? null)) {
            $value = (float) $forecast['reading']['value'];
            $low = $value < (float) config('climate.monitoring.low_forecast_rain_mm', 10);
            $item['lines'][] = '7-day rain forecast: '.number_format($value, 1).' mm ('.$this->date($forecast['reading']['period_start'] ?? null)
                .' to '.$this->date($forecast['reading']['period_end'] ?? null).').';
        } else {
            $item['lines'][] = '7-day rain forecast: unavailable ('.($forecast['label'] ?? 'not synced yet').').';
        }
        if (($chirps['usable'] ?? false) && is_numeric($chirps['reading']['value'] ?? null)) {
            $row = $chirps['reading'];
            $line = '30-day rainfall estimate: '.number_format((float) $row['value'], 1).' mm';
            $baseline = $row['baseline_value'] ?? null;
            $years = (int) ($row['metadata']['baseline_years'] ?? 0);
            $requested = (int) ($row['metadata']['baseline_years_requested'] ?? 0);
            if (is_numeric($baseline) && (float) $baseline > 0 && $requested >= 3 && $years === $requested) {
                $comparable = true;
                $percent = 100 * (((float) $row['value'] - (float) $baseline) / (float) $baseline);
                $below = $percent <= -(float) config('climate.monitoring.rainfall_deficit_percent', 30);
                $line .= ', '.number_format(abs($percent), 0).'% '.($percent < 0 ? 'below' : 'above').' the '.$years.'-year reference';
            }
            $item['lines'][] = $line.'.';
        } else {
            $item['lines'][] = '30-day rainfall estimate: unavailable ('.($chirps['label'] ?? 'not synced yet').').';
        }
        $usable = ($forecast['usable'] ?? false) || ($chirps['usable'] ?? false);
        $rainfall = ! $usable ? 'unavailable' : ($below ? 'below_reference' : ($low ? 'little_rain_forecast'
            : (($forecast['usable'] ?? false) || $comparable ? 'no_dry_signal' : 'unavailable')));
        $item['status'] = ! $usable ? 'unavailable' : (in_array($rainfall, ['below_reference', 'little_rain_forecast'], true) ? 'attention' : 'available');
        $item['text'] = match ($rainfall) {
            'below_reference' => 'Recent rain around the municipality is below its multi-year reference.',
            'little_rain_forecast' => 'Little rain is forecast around the municipality this week.',
            'no_dry_signal' => 'Current municipality readings show no dry-weather trigger.',
            default => $usable ? 'Municipality rainfall readings are incomplete for a comparison.' : 'No current municipality weather or rainfall reading. Missing data is not zero rainfall.',
        };

        return [$item, $rainfall];
    }

    private function harvestItem(FarmPlot $plot, User $user): array
    {
        $item = ['key' => 'harvests', 'title' => 'Harvest history', 'status' => 'unavailable', 'scope' => 'Harvest records for this parcel', 'lines' => []];
        try {
            if (! Schema::hasTable('harvest_records') || ! Schema::hasColumn('harvest_records', 'farm_plot_id')) {
                $item['text'] = 'Harvest records are not available.';

                return $item;
            }
            $query = $this->access->scope(HarvestRecord::query(), $user)->where('farm_plot_id', $plot->id)
                ->where('farmer_id', $plot->farmer_id)->where('municipality_id', (int) $plot->farmer->municipality_id);
            $total = (clone $query)->count();
            $rows = $query->orderByDesc('harvest_year')->orderByDesc('date_harvested')->orderByDesc('id')->limit(self::HARVEST_LIMIT)
                ->get(['id', 'commodity', 'season', 'harvest_year', 'date_harvested', 'quantity', 'quantity_unit', 'area_harvested_ha']);
        } catch (Throwable) {
            $item['text'] = 'Harvest records could not be read right now.';

            return $item;
        }
        if ($total === 0) {
            $item['text'] = 'No harvest is recorded for this parcel. This does not mean nothing was harvested.';

            return $item;
        }
        $item['status'] = 'available';
        $item['text'] = $total.' '.($total === 1 ? 'harvest is' : 'harvests are').' recorded for this parcel'.($total > self::HARVEST_LIMIT ? '; the latest '.self::HARVEST_LIMIT.' are shown' : '').'. Units are kept separate.';
        foreach ($rows as $row) {
            $item['lines'][] = ($row->harvest_year ?: 'Year not recorded').($row->season ? ' '.$row->season.' season' : '').' · '
                .(HarvestRecord::COMMODITY_LABELS[$row->commodity] ?? 'Other commodity').' · '
                .($row->quantity !== null ? rtrim(rtrim(number_format((float) $row->quantity, 3, '.', ','), '0'), '.').' '
                    .(HarvestRecord::QUANTITY_UNIT_LABELS[$row->quantity_unit ?? 'kg'] ?? 'units') : 'quantity not recorded')
                .($row->date_harvested ? ' · harvested '.$this->date($row->date_harvested->toDateString()) : '');
        }

        return $item;
    }

    private function nextStep(array $stress, array $suitability): array
    {
        $signals = array_column($stress['signals'] ?? [], null, 'key');
        foreach (['field_condition', 'water_supply', 'flowering', 'inspection_due', 'ndvi_decline', 'ndmi_decline'] as $key) {
            if (isset($signals[$key])) {
                return ['title' => $signals[$key]['title'], 'text' => $signals[$key]['detail'], 'source' => 'Field-check guidance'];
            }
        }
        foreach ($signals as $signal) {
            return ['title' => $signal['title'], 'text' => $signal['detail'], 'source' => 'Field-check guidance (municipality evidence)'];
        }
        if (($stress['missing'] ?? []) !== []) {
            return ['title' => 'Record a current field check', 'text' => 'Still needed: '.implode('; ', array_slice($stress['missing'], 0, 3)).'.', 'source' => 'Field-check guidance'];
        }
        if (($suitability['status'] ?? null) === 'review_needed') {
            return ['title' => 'Review crop conditions', 'text' => 'Ask an agriculture officer to review: '.implode(', ', $suitability['review']).'.', 'source' => 'Crop suitability screen'];
        }

        return ['title' => $stress['label'] ?? 'Keep checking this parcel', 'text' => $stress['summary'] ?? 'Continue routine field checks.', 'source' => 'Field-check guidance'];
    }

    private function date(?string $value): string
    {
        $date = is_string($value) ? ParcelSuitability::date(substr($value, 0, 10)) : null;

        return $date ? $date->format('M j, Y') : 'date not recorded';
    }
}
