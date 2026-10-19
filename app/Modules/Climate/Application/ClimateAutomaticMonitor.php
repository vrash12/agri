<?php

namespace App\Modules\Climate\Application;

use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Throwable;

/** Transparent field-inspection prompts from dated evidence, never official drought levels. */
final class ClimateAutomaticMonitor
{
    public function summarize(array $evidence): array
    {
        $weather = $this->source($evidence['open_meteo'] ?? null, 'open_meteo');
        $rainfall = $this->source($evidence['chirps'] ?? null, 'chirps');
        $signals = [];
        if ($weather['usable']) {
            $row = $weather['reading'];
            $temperature = $row['metadata']['temperature_max_c'] ?? null;
            $hot = (float) config('climate.monitoring.high_temperature_c', 35);
            $dry = (float) config('climate.monitoring.low_forecast_rain_mm', 10);
            if ($this->number($temperature) && (int) ($row['metadata']['temperature_days'] ?? 0) === 7 && $temperature >= $hot) {
                $signals[] = ['title' => 'Hot weather ahead',
                    'detail' => 'Forecast high: '.number_format($temperature, 1).'°C. Check water supply and field conditions.',
                    'source' => 'Open-Meteo forecast', 'date' => $this->date($row['period_end'])];
            }
            if ($row['value'] < $dry) {
                $signals[] = ['title' => 'Little rain forecast',
                    'detail' => number_format($row['value'], 1).' mm over seven days. Review irrigation availability.',
                    'source' => 'Open-Meteo forecast', 'date' => $this->date($row['period_end'])];
            }
        }
        if ($rainfall['usable']) {
            $row = $rainfall['reading'];
            $baseline = $row['baseline_value'] ?? null;
            $years = (int) ($row['metadata']['baseline_years'] ?? 0);
            $requested = (int) ($row['metadata']['baseline_years_requested'] ?? 0);
            if ($this->number($baseline) && $baseline > 0 && $requested >= 3 && $years === $requested) {
                // Recompute from complete totals rather than trusting a supplied percentage.
                $percent = 100 * (($row['value'] - $baseline) / $baseline);
                if ($percent <= -(float) config('climate.monitoring.rainfall_deficit_percent', 30)) {
                    $signals[] = ['title' => 'Recent rain below reference',
                        'detail' => number_format(abs($percent), 0).'% less rain than the same period in the '.$years.'-year reference. Verify local conditions.',
                        'source' => 'CHIRPS rainfall estimate', 'date' => $this->date($row['period_end'])];
                }
            }
        }
        $usable = (int) $weather['usable'] + (int) $rainfall['usable'];
        $state = $signals !== [] ? 'attention' : ($usable > 0 ? 'monitoring' : 'waiting');

        return [
            'state' => $state,
            'label' => match ($state) {
                'attention' => 'Check field conditions', 'monitoring' => 'Keep monitoring', default => 'Waiting for current readings'
            },
            'summary' => match ($state) {
                'attention' => 'The latest readings suggest a field or water-supply check.',
                'monitoring' => 'No monitoring trigger was found in the available readings. Continue routine field checks.',
                default => 'Automatic checks need complete, recent readings for this area. Missing data is not zero rainfall.',
            },
            'signals' => $signals, 'sources' => ['open_meteo' => $weather, 'chirps' => $rainfall],
            'coverage' => $usable.' of 2 rainfall sources ready',
            'limits' => 'These are inspection prompts, not an official El Niño or drought declaration. Confirm crop stage, irrigation and conditions on the farm.',
            'thresholds' => ['temperature' => (float) config('climate.monitoring.high_temperature_c', 35),
                'forecast_rain' => (float) config('climate.monitoring.low_forecast_rain_mm', 10),
                'rainfall_deficit' => (float) config('climate.monitoring.rainfall_deficit_percent', 30)],
        ];
    }

    private function source(?array $row, string $provider): array
    {
        $result = ['usable' => false, 'label' => 'Not synced yet', 'reading' => $row];
        if ($row === null) {
            return $result;
        }
        $result['label'] = 'Waiting for usable data';
        if (($row['status'] ?? '') !== 'available' || ! $this->number($row['value'] ?? null) || $row['value'] < 0) {
            return $result;
        }
        if (! ($row['context_current'] ?? false) || ! ($row['metadata']['coverage_complete'] ?? false)) {
            $result['label'] = 'Needs a fresh complete reading';

            return $result;
        }
        if (empty($row['fetched_at']) || empty($row['period_start']) || empty($row['period_end'])) {
            $result['label'] = 'Reading dates need review';

            return $result;
        }
        try {
            $fetched = CarbonImmutable::parse($row['fetched_at'], 'UTC');
            $start = CarbonImmutable::parse($row['period_start'], 'UTC')->startOfDay();
            $end = CarbonImmutable::parse($row['period_end'], 'UTC')->startOfDay();
            $today = CarbonImmutable::parse(LocalTime::now()->toDateString(), 'UTC');
            $maxAge = $provider === 'open_meteo' ? 12 : 48;
            $stale = $fetched->gt(now()->utc()->addMinutes(5)) || $fetched->lt(now()->utc()->subHours($maxAge)) || ($row['metadata']['is_stale'] ?? false);
            if ($provider === 'open_meteo') {
                $complete = $start->addDays(6)->equalTo($end) && $start->lte($today) && $end->gte($today)
                    && (int) ($row['metadata']['forecast_days'] ?? 0) === 7 && (int) ($row['metadata']['rain_days'] ?? 0) === 7;
            } else {
                $complete = $start->addDays(29)->equalTo($end) && $end->lt($today) && $end->gte($today->subDays(21))
                    && (int) ($row['metadata']['days_available'] ?? 0) === 30;
            }
            if (! $complete || $stale) {
                $result['label'] = $stale ? 'Older reading · awaiting refresh' : 'Period incomplete or too old';

                return $result;
            }
        } catch (Throwable) {
            $result['label'] = 'Reading dates need review';

            return $result;
        }

        return ['usable' => true, 'label' => 'Current reading', 'reading' => $row];
    }

    private function number(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value);
    }

    private function date(mixed $value): string
    {
        return CarbonImmutable::parse($value)->toDateString();
    }
}
