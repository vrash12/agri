<?php

namespace App\Console\Commands;

use App\Models\ClimateRiskSourceReading;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Services\ChirpsRainfallService;
use App\Services\WeatherForecastService;
use App\Support\ClimateRiskSourceRecorder;
use App\Support\LocalTime;
use App\Support\ParcelVegetationSnapshot;
use App\Support\SentinelImagery;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class RefreshClimateSources extends Command
{
    protected $signature = 'climate:refresh-sources
        {--provider=* : Provider(s) to refresh: open-meteo, chirps, or sentinel2}
        {--municipality=* : Limit refresh to municipality IDs}
        {--force : Refresh even when the source is inside its normal freshness window}';

    protected $description = 'Refresh cached climate evidence without creating office assessments';

    public function handle(
        WeatherForecastService $weather,
        ChirpsRainfallService $chirps,
        ClimateRiskSourceRecorder $recorder,
        SentinelImagery $sentinel,
        ParcelVegetationSnapshot $vegetation
    ): int {
        if (! Schema::hasTable('climate_risk_source_readings')) {
            $this->error('The climate source readings migration is not installed.');

            return self::FAILURE;
        }

        $providers = $this->providers();
        $ids = collect($this->option('municipality'))
            ->filter(fn ($id) => ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)->values()->all();
        $municipalities = Municipality::query()->active()->whereHas('supervisingProvince', fn ($query) => $query->active())
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->with('activeBoundary')->orderBy('id')->get();

        $totals = ['open_meteo' => 0, 'chirps' => 0, 'sentinel2' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($municipalities as $municipality) {
            $boundary = $municipality->activeBoundary;
            $coordinates = $boundary && is_numeric($boundary->centroid_lat) && is_numeric($boundary->centroid_lng)
                ? ['latitude' => (float) $boundary->centroid_lat, 'longitude' => (float) $boundary->centroid_lng] : null;
            if ($coordinates === null && array_intersect($providers, ['open-meteo', 'chirps']) !== []) {
                $totals['skipped']++;
                $this->line($municipality->name.': skipped (no active boundary reference point).');

                continue;
            }

            if (in_array('open-meteo', $providers, true)) {
                $this->refreshOpenMeteo($municipality, $coordinates, $weather, $recorder, $totals);
            }
            if (in_array('chirps', $providers, true)) {
                $this->refreshChirps($municipality, $coordinates, $chirps, $recorder, $totals);
            }
            if (in_array('sentinel2', $providers, true)) {
                $this->refreshSentinel($municipality, $sentinel, $vegetation, $recorder, $totals);
            }
        }

        $this->line(sprintf('Climate source refresh complete: Open-Meteo %d, CHIRPS %d, Sentinel-2 %d, skipped %d, failed %d.',
            $totals['open_meteo'], $totals['chirps'], $totals['sentinel2'], $totals['skipped'], $totals['failed']));

        return $totals['failed'] > 0 && $totals['open_meteo'] + $totals['chirps'] + $totals['sentinel2'] === 0
            ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, string> */
    private function providers(): array
    {
        $requested = collect($this->option('provider'))->map(fn ($provider) => strtolower(trim((string) $provider)))
            ->filter(fn ($provider) => in_array($provider, ['open-meteo', 'chirps', 'sentinel2'], true))->unique()->values()->all();
        if ($requested !== []) {
            return $requested;
        }

        return ['open-meteo', 'chirps'];
    }

    private function refreshOpenMeteo(Municipality $municipality, ?array $coordinates, WeatherForecastService $weather, ClimateRiskSourceRecorder $recorder, array &$totals): void
    {
        if (! config('climate.sources.open_meteo.enabled', true) || $coordinates === null || (! $this->option('force') && $this->fresh($municipality, 'open_meteo', 'forecast_7d'))) {
            return;
        }
        try {
            $forecast = $weather->forMunicipalityAt($municipality, $coordinates, true);
            if (! ($forecast['available'] ?? false) || ! is_array($forecast['daily'] ?? null) || $forecast['daily'] === []) {
                $totals['failed']++;

                return;
            }
            $daily = $forecast['daily'];
            $summary = (array) ($forecast['summary'] ?? []);
            $recorder->municipality($municipality, 'open_meteo', 'forecast_7d', [
                'observed_on' => LocalTime::now()->toDateString(),
                'period_start' => $daily[0]['date'] ?? null,
                'period_end' => $daily[count($daily) - 1]['date'] ?? null,
                'value' => $summary['seven_day_rain'] ?? null,
                'unit' => 'mm',
                'status' => 'available',
                'source_label' => 'Open-Meteo seven-day forecast',
                'source_url' => 'https://open-meteo.com/en/docs',
                'metadata' => [
                    'forecast_days' => count($daily), 'daily_rain_mm' => $summary['seven_day_rain'] ?? null,
                    'temperature_max_c' => $summary['maximum_temperature'] ?? null,
                    'rain_probability' => $summary['maximum_rain_probability'] ?? null,
                ],
            ]);
            $totals['open_meteo']++;
        } catch (Throwable $exception) {
            report($exception);
            $totals['failed']++;
        }
    }

    private function refreshChirps(Municipality $municipality, ?array $coordinates, ChirpsRainfallService $chirps, ClimateRiskSourceRecorder $recorder, array &$totals): void
    {
        if (! config('climate.sources.chirps.enabled', true) || $coordinates === null || (! $this->option('force') && $this->fresh($municipality, 'chirps', 'rainfall_30d'))) {
            return;
        }
        try {
            $reading = $chirps->forMunicipality($municipality, $coordinates, true);
            if (! ($reading['available'] ?? false)) {
                $totals['failed']++;

                return;
            }
            $recorder->municipality($municipality, 'chirps', 'rainfall_30d', [
                'observed_on' => $reading['observed_on'], 'period_start' => $reading['period_start'], 'period_end' => $reading['period_end'],
                'value' => $reading['rainfall_mm'], 'baseline_value' => $reading['baseline_mm'], 'anomaly_percent' => $reading['anomaly_percent'],
                'unit' => 'mm', 'status' => 'available', 'source_label' => 'CHIRPS v3 area-average rainfall',
                'source_url' => $reading['source_url'],
                'metadata' => ['period_days' => 30, 'baseline_years' => $reading['baseline_years'], 'days_available' => $reading['days_available']],
            ]);
            $totals['chirps']++;
        } catch (Throwable $exception) {
            report($exception);
            $totals['failed']++;
        }
    }

    private function refreshSentinel(Municipality $municipality, SentinelImagery $sentinel, ParcelVegetationSnapshot $vegetation, ClimateRiskSourceRecorder $recorder, array &$totals): void
    {
        if (! config('climate.sources.sentinel2.enabled', false) || ! $sentinel->configured()) {
            return;
        }
        $from = CarbonImmutable::now('UTC')->subDays(31)->toDateString();
        $to = CarbonImmutable::now('UTC')->subDay()->toDateString();
        $plots = FarmPlot::query()->with('farmer.municipality')->whereHas('farmer', fn ($query) => $query->where('municipality_id', $municipality->getKey()))
            ->whereNotNull('polygon_json')->limit((int) config('climate.sources.sentinel2.max_plots', 10))->get();
        foreach ($plots as $plot) {
            if (! $this->option('force') && $this->fresh($municipality, 'sentinel2', 'ndvi', $plot->id)) {
                continue;
            }
            try {
                $result = $sentinel->statistics($plot, $from, $to, 40);
                $vegetation->remember($plot, $result);
                $summary = $vegetation->forPlot($plot);
                if ($summary) {
                    $recorder->plot($plot, 'sentinel2', 'ndvi', [
                        'observed_on' => $summary['observed_on'], 'value' => $summary['mean'], 'unit' => 'NDVI',
                        'status' => 'available', 'source_label' => 'Copernicus Sentinel-2 Level-2A NDVI',
                        'source_url' => 'https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Statistical.html',
                        'metadata' => ['is_stale' => $summary['is_stale'], 'message' => 'Greenness reading; not a drought diagnosis.'],
                    ]);
                    $totals['sentinel2']++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $totals['failed']++;
            }
        }
    }

    private function fresh(Municipality $municipality, string $provider, string $metric, ?int $plotId = null): bool
    {
        if (! Schema::hasTable('climate_risk_source_readings')) {
            return false;
        }
        $hours = $provider === 'open_meteo'
            ? (int) config('climate.sources.open_meteo.refresh_hours', 6)
            : ($provider === 'chirps' ? (int) config('climate.sources.chirps.refresh_hours', 24) : (int) config('climate.sources.sentinel2.refresh_hours', 168));

        return ClimateRiskSourceReading::query()->where('municipality_id', $municipality->getKey())->where('provider', $provider)
            ->where('metric', $metric)->when($plotId, fn ($query) => $query->where('farm_plot_id', $plotId))
            ->where('fetched_at', '>=', now()->subHours(max(1, $hours)))->exists();
    }
}
