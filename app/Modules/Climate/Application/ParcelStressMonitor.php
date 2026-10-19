<?php

namespace App\Modules\Climate\Application;

/** Combines field checks with existing evidence without requesting satellite processing. */
final class ParcelStressMonitor
{
    public function summarize(?array $assessment, array $climateMonitoring, array $satelliteHistory): array
    {
        $assessment ??= [];
        $current = ($assessment['context_current'] ?? true) === true;
        $observed = $current ? ParcelSuitability::freshDate($assessment['observed_on'] ?? null, 'field') : null;
        $stage = $observed ? ParcelSuitability::choice($assessment['crop_stage'] ?? null,
            ['not_planted', 'establishment', 'vegetative', 'flowering', 'grain_filling', 'mature', 'harvested']) : null;
        $stageLabels = ['not_planted' => 'Not planted', 'establishment' => 'Newly planted', 'vegetative' => 'Leaf growth',
            'flowering' => 'Flowering', 'grain_filling' => 'Grain / pod filling', 'mature' => 'Mature', 'harvested' => 'Harvested'];
        $planted = $observed ? ParcelSuitability::date($assessment['planted_on'] ?? null) : null;
        if ($planted && $planted->gt($observed)) {
            $planted = null;
        }
        // A crop season may cross New Year. Elapsed crop age, not the selected calendar year,
        // identifies a stage record that needs confirmation for these annual crops.
        $oldCycle = $planted && $planted->diffInDays($observed) > 366;
        if ($oldCycle) {
            $stage = null;
        }
        $active = in_array($stage, ['establishment', 'vegetative', 'flowering', 'grain_filling'], true);
        $inactive = in_array($stage, ['not_planted', 'harvested'], true);
        $condition = $observed ? ParcelSuitability::choice($assessment['field_condition'] ?? null,
            ['normal', 'wilting', 'yellowing', 'waterlogged']) : null;
        $waterDate = $observed ? ParcelSuitability::freshDate($assessment['water_observed_on'] ?? null, 'water') : null;
        $waterSourced = is_string($assessment['water_source'] ?? null) && trim($assessment['water_source']) !== '';
        $water = $waterDate && $waterDate->lte($observed) && $waterSourced
            ? ParcelSuitability::choice($assessment['water_availability'] ?? null, ['reliable', 'limited', 'unavailable']) : null;
        $missing = [];
        if (! $observed) {
            $missing[] = $current ? 'A field check from the last '.config('agronomy.freshness_days.field', 14).' days'
                : 'A field check for the current parcel boundary';
        }
        if (! $stage) {
            $missing[] = $oldCycle ? 'Confirm crop stage and planting date; this annual crop record is over a year old' : 'Current crop stage';
        }
        if (! $condition) {
            $missing[] = 'Current field condition';
        }
        if (! $water) {
            $missing[] = 'A recent, sourced water-supply check';
        }
        if ($active && ! $planted) {
            $missing[] = 'Planting date for matching satellite readings to this crop';
        }

        // Recheck freshness rather than perpetuating a previously computed municipal prompt.
        $sourceRows = [];
        foreach (['open_meteo', 'chirps'] as $provider) {
            $reading = $climateMonitoring['sources'][$provider]['reading'] ?? null;
            if (is_array($reading)) {
                $sourceRows[$provider] = $reading;
            }
        }
        $climate = (new ClimateAutomaticMonitor)->summarize($sourceRows);
        $signals = [];
        foreach ($climate['signals'] as $signal) {
            $signals[] = ['key' => 'municipality_'.count($signals), 'title' => $signal['title'],
                'detail' => $signal['detail'].' This is area evidence; verify this parcel on the farm.',
                'scope' => 'Municipality', 'date' => $signal['date']];
        }
        $fieldConcern = false;
        if ($active && in_array($water, ['limited', 'unavailable'], true)) {
            $fieldConcern = true;
            $signals[] = ['key' => 'water_supply', 'title' => 'Check the crop’s water supply',
                'detail' => 'The latest field record reports '.($water === 'limited' ? 'limited water' : 'no available water').'. Confirm soil moisture and the next usable water supply.',
                'scope' => 'Parcel field check', 'date' => $waterDate->toDateString()];
        }
        if ($observed && ! $inactive && in_array($condition, ['wilting', 'yellowing', 'waterlogged'], true)) {
            $fieldConcern = true;
            $signals[] = ['key' => 'field_condition', 'title' => match ($condition) {
                'wilting' => 'Wilting was reported', 'yellowing' => 'Yellowing was reported', default => 'Waterlogging was reported',
            }, 'detail' => match ($condition) {
                'wilting' => 'Inspect plants and soil moisture. Wilting can have more than one cause.',
                'yellowing' => 'Check crop age, roots, nutrients and pests. Yellowing alone does not confirm drought.',
                default => 'Check flood depth and drainage. Standing water in lowland rice must be judged against its growth stage.',
            }, 'scope' => 'Parcel field check', 'date' => $observed->toDateString()];
        }
        if ($active && $stage === 'flowering' && ($fieldConcern || $climate['signals'] !== [])) {
            $signals[] = ['key' => 'flowering', 'title' => 'Flowering crop: confirm water promptly',
                'detail' => ($assessment['crop'] ?? '') === 'rice'
                    ? 'Rice is especially sensitive to water shortage around flowering. Ask the agriculture office to inspect the field.'
                    : 'Confirm the crop’s water needs with the agriculture office while flowers and grain or pods are forming.',
                'scope' => 'Crop stage', 'date' => $observed->toDateString()];
        }

        $satellite = [];
        foreach (['ndvi' => 'Greenness (NDVI)', 'ndmi' => 'Plant moisture indicator (NDMI)',
            'savi' => 'Sparse-cover greenness (SAVI)', 'evi' => 'Dense-cover greenness (EVI)'] as $index => $label) {
            $trend = $this->trend($index, $label, $satelliteHistory[$index] ?? [], $planted?->toDateString());
            if ($trend['status'] === 'comparable' && in_array($index, ['ndvi', 'ndmi'], true)
                && $trend['delta'] < 0 && $active && $planted && ($fieldConcern || $climate['signals'] !== [])) {
                $signals[] = ['key' => $index.'_decline', 'title' => strtoupper($index).' decreased between usable pictures',
                    'detail' => 'The change supports a field visit alongside the water or weather concern. It cannot identify the cause or confirm drought.',
                    'scope' => 'Parcel satellite check', 'date' => $trend['observations'][count($trend['observations']) - 1]['date']];
            }
            if (in_array($stage, ['mature', 'harvested', 'not_planted'], true)) {
                $trend['detail'] .= ' The recorded crop stage can explain lower greenness; no satellite crop-stress flag is inferred.';
            }
            if (! $planted) {
                $trend['detail'] .= ' Record a planting date before treating this as a change within one crop cycle.';
            }
            $satellite[] = $trend;
        }
        $followUp = $this->followUp($assessment, $current);
        if ($followUp['overdue']) {
            $signals[] = ['key' => 'inspection_due', 'title' => 'Field inspection is due',
                'detail' => 'Complete the planned visit and record what was found. A due visit does not confirm crop damage.',
                'scope' => 'Office follow-up', 'date' => $followUp['due_on']];
        }
        $hasCurrentSatellite = collect($satellite)->contains(fn (array $row) => in_array($row['status'], ['single', 'comparable', 'not_comparable'], true));
        $state = $signals !== [] ? 'attention' : ($inactive ? 'inactive'
            : ($observed || $hasCurrentSatellite || $climate['state'] === 'monitoring' ? 'monitoring' : 'waiting'));

        return ['status' => $state,
            'label' => match ($state) {
                'attention' => 'Field check recommended', 'monitoring' => 'Keep checking this parcel',
                'inactive' => 'No growing crop recorded', default => 'Add current field evidence',
            },
            'summary' => match ($state) {
                'attention' => 'Review the dated evidence below and confirm conditions on the farm.',
                'monitoring' => 'No combined inspection trigger was found in the available evidence. Missing evidence still needs attention.',
                'inactive' => 'The latest field record says this parcel is not planted or was harvested. Update it when a new crop starts.',
                default => 'Record crop stage, field condition and water availability. Existing weather and satellite checks will be shown here when usable.',
            },
            'observed_on' => $observed?->toDateString(), 'stage_label' => $stageLabels[$stage] ?? 'Stage not confirmed',
            'signals' => $signals, 'missing' => $missing, 'satellite' => $satellite, 'follow_up' => $followUp,
            'limits' => 'Inspection support only. Weather describes the surrounding area; satellite changes may reflect crop growth, harvest, clouds or other causes. Confirm stress and its cause on the farm. This does not declare drought or mark a parcel as affected.',
        ];
    }

    private function trend(string $index, string $label, mixed $rows, ?string $plantedOn): array
    {
        $accepted = [];
        foreach (is_array($rows) ? array_slice($rows, -90) : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $date = ParcelSuitability::date($row['date'] ?? null);
            // NDVI/NDMI have physical normalized ranges; SAVI/EVI may legitimately exceed them.
            $bound = in_array($index, ['ndvi', 'ndmi'], true) ? 1 : PHP_FLOAT_MAX;
            $mean = ParcelSuitability::number($row['mean'] ?? null, -$bound, $bound);
            $pixels = ParcelSuitability::number($row['valid_pixels'] ?? null, 1, PHP_INT_MAX);
            if (! $date || $date->gt(ParcelSuitability::today()) || $mean === null || $pixels === null || floor($pixels) !== $pixels
                || ($row['context_current'] ?? true) !== true || ! is_string($row['series_key'] ?? null) || $row['series_key'] === '') {
                continue;
            }
            if ($plantedOn && $date->toDateString() < $plantedOn) {
                continue;
            }
            $accepted[] = ['date' => $date->toDateString(), 'mean' => $mean, 'valid_pixels' => (int) $pixels,
                'series_key' => $row['series_key'], 'is_stale' => ($row['is_stale'] ?? false) === true,
                'checked_at' => is_string($row['checked_at'] ?? null) ? $row['checked_at'] : null];
        }
        usort($accepted, fn (array $a, array $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['checked_at'] ?? '', $b['checked_at'] ?? ''));
        $latest = $accepted !== [] ? $accepted[count($accepted) - 1] : null;
        $status = 'unavailable';
        $detail = 'No usable saved pictures for this crop period. Run a satellite field check when needed.';
        $delta = null;
        $chart = [];
        if ($latest) {
            $sameSeries = array_filter($accepted, fn (array $row) => $row['series_key'] === $latest['series_key']);
            $byDate = [];
            foreach ($sameSeries as $row) {
                $byDate[$row['date']] = $row;
            }
            $chart = array_values($byDate);
            $status = count($accepted) > 1 ? 'not_comparable' : 'single';
            $detail = 'One usable picture or no suitably spaced comparison. More comparable pictures are needed to describe a change.';
            $latestDate = ParcelSuitability::date($latest['date']);
            $previous = null;
            foreach (array_reverse($chart) as $row) {
                $gap = ParcelSuitability::date($row['date'])->diffInDays($latestDate);
                if ($gap >= (int) config('agronomy.satellite_minimum_gap_days', 3)
                    && $gap <= (int) config('agronomy.satellite_maximum_gap_days', 45)) {
                    $previous = $row;
                    break;
                }
            }
            if ($latest['is_stale'] || ! ParcelSuitability::freshDate($latest['date'], 'satellite')) {
                $status = 'stale';
                $detail = 'The latest usable picture is older than the monitoring window. It remains historical evidence only.';
            } elseif ($previous) {
                $difference = $latest['mean'] - $previous['mean'];
                if (is_finite($difference)) {
                    $status = 'comparable';
                    $delta = round($difference, 6);
                    $detail = 'Change from '.$previous['date'].' to '.$latest['date'].': '.($delta > 0 ? '+' : '').rtrim(rtrim(number_format($delta, 6, '.', ''), '0'), '.').'. Same index and analysis settings; conditions and visible pixels can still differ.';
                }
            }
        }

        return ['index' => $index, 'label' => $label, 'status' => $status,
            'label_status' => match ($status) {
                'comparable' => 'Comparable pictures', 'single' => 'One picture', 'not_comparable' => 'More comparable pictures needed',
                'stale' => 'Older pictures', default => 'No saved pictures',
            }, 'observations' => array_map(fn (array $row) => array_intersect_key($row, array_flip(['date', 'mean', 'valid_pixels'])), $chart),
            'delta' => $delta, 'detail' => $detail];
    }

    private function followUp(array $assessment, bool $current): array
    {
        $due = $current ? ParcelSuitability::date($assessment['next_inspection_on'] ?? null) : null;
        $status = ParcelSuitability::choice($assessment['follow_up_status'] ?? null, ['open', 'completed']);
        $overdue = $current && $status === 'open' && $due && $due->lte(ParcelSuitability::today());

        return ['status' => $current ? $status : null, 'due_on' => $due?->toDateString(), 'overdue' => (bool) $overdue,
            'label' => ! $current ? 'Record a new inspection for the current parcel' : ($status === 'completed' ? 'Last follow-up completed'
                : ($due ? ($overdue ? 'Inspection due' : 'Next inspection: '.$due->toDateString()) : 'No next inspection recorded'))];
    }
}
