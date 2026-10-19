<?php

namespace App\Console\Commands;

use App\Models\ClimateRiskSourceReading;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\User;
use App\Modules\Climate\Application\ClimateEvidenceContext;
use App\Modules\Climate\Application\ClimateRefreshStatus;
use App\Modules\Climate\Application\ClimateRiskSourceRecorder;
use App\Modules\Climate\Application\ParcelVegetationSnapshot;
use App\Modules\Climate\Application\SentinelImagery;
use App\Modules\Climate\Infrastructure\ChirpsRainfallService;
use App\Modules\Climate\Infrastructure\WeatherForecastService;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class RefreshClimateSources extends Command
{
    private int $satelliteRemaining = 0;

    private float $refreshDeadline = 0;

    private int $satelliteScanRemaining = 500;

    protected $signature = 'climate:refresh-sources
        {--provider=* : Provider(s) to refresh: open-meteo, chirps, or sentinel2}
        {--municipality=* : Limit refresh to municipality IDs}
        {--batch-size= : Maximum municipalities in one scheduled run}
        {--force : Refresh even when the source is inside its normal freshness window}';

    protected $description = 'Refresh cached climate evidence without creating office assessments';

    public function handle(
        WeatherForecastService $weather,
        ChirpsRainfallService $chirps,
        ClimateRiskSourceRecorder $recorder,
        SentinelImagery $sentinel,
        ParcelVegetationSnapshot $vegetation
    ): int {
        $providers = $this->providers();
        $rawIds = (array) $this->option('municipality');
        $batchSize = $this->option('batch-size');
        if ($providers === [] || count($rawIds) > 200 || collect($rawIds)->contains(fn ($id) => ! preg_match('/^[1-9]\d{0,9}$/', (string) $id))
            || ($batchSize !== null && (! ctype_digit((string) $batchSize) || (int) $batchSize < 1 || (int) $batchSize > 200))) {
            $this->error('Use known providers, positive municipality IDs and a batch size from 1 to 200. No refresh ran.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('climate_risk_source_readings')) {
            $this->error('The climate source readings migration is not installed.');

            return self::FAILURE;
        }

        $lock = Cache::lock('climate.sources.command.v2', 900);
        if (! $lock->get()) {
            $this->line('A climate source refresh is already running. No duplicate work started.');

            return self::SUCCESS;
        }
        try {
            return $this->refresh($providers, array_values(array_unique(array_map('intval', $rawIds))), $weather, $chirps, $recorder, $sentinel, $vegetation);
        } finally {
            $lock->release();
        }
    }

    private function refresh(array $providers, array $ids, WeatherForecastService $weather, ChirpsRainfallService $chirps, ClimateRiskSourceRecorder $recorder, SentinelImagery $sentinel, ParcelVegetationSnapshot $vegetation): int
    {
        $allMunicipalities = Municipality::query()->active()->whereHas('supervisingProvince', fn ($query) => $query->active())
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($ids === [], fn ($query) => $query->where(fn ($active) => $active->whereHas('farmers')
                ->orWhereHas('users', fn ($users) => $users->where('is_active', true)->whereIn('role', User::MUNICIPAL_ROLES)->where('visitor_mode', false))
                ->orWhereHas('climateRiskAssessments')))
            ->with('activeBoundary')->orderBy('id')->get();
        if ($ids !== [] && $allMunicipalities->count() !== count($ids)) {
            $this->error('One or more municipalities are unavailable or outside an active province. No refresh ran.');

            return self::FAILURE;
        }
        $municipalities = $this->batch($allMunicipalities, $providers, $ids);
        $activeProviders = array_values(array_filter($providers, fn ($provider) => (bool) config('climate.sources.'.str_replace('-', '_', $provider).'.enabled', $provider !== 'sentinel2')));
        $heartbeat = app(ClimateRefreshStatus::class);
        foreach ($activeProviders as $provider) {
            $heartbeat->started($provider);
        }
        $deadline = microtime(true) + min(600, max(30, (int) config('climate.refresh_runtime_seconds', 240)));
        $this->refreshDeadline = $deadline;
        $this->satelliteRemaining = min(50, max(1, (int) config('climate.sources.sentinel2.max_plots', 10)));
        $this->satelliteScanRemaining = 500;
        $totals = ['open_meteo' => 0, 'chirps' => 0, 'sentinel2' => 0, 'skipped' => 0, 'failed' => 0];
        $providerTotals = array_fill_keys($activeProviders, $totals);
        foreach ($municipalities as $municipality) {
            if (microtime(true) >= $deadline) {
                $this->line('This run reached its time allowance; the next scheduled run continues the remaining areas.');
                break;
            }
            $boundary = $municipality->activeBoundary;
            $coordinates = $boundary && is_numeric($boundary->centroid_lat) && is_numeric($boundary->centroid_lng)
                ? ['latitude' => (float) $boundary->centroid_lat, 'longitude' => (float) $boundary->centroid_lng] : null;
            if ($coordinates === null && array_intersect($activeProviders, ['open-meteo', 'chirps']) !== []) {
                foreach (array_intersect($activeProviders, ['open-meteo', 'chirps']) as $provider) {
                    $providerTotals[$provider]['skipped']++;
                }
                $this->line($municipality->name.': skipped (no active boundary reference point).');
            }

            if (in_array('open-meteo', $activeProviders, true)) {
                $this->refreshOpenMeteo($municipality, $coordinates, $weather, $recorder, $providerTotals['open-meteo']);
            }
            if (in_array('chirps', $activeProviders, true) && microtime(true) < $deadline) {
                $this->refreshChirps($municipality, $coordinates, $chirps, $recorder, $providerTotals['chirps']);
            }
            if (in_array('sentinel2', $activeProviders, true) && microtime(true) < $deadline) {
                $this->refreshSentinel($municipality, $sentinel, $vegetation, $recorder, $providerTotals['sentinel2']);
            }
            if ($ids === [] && $allMunicipalities->isNotEmpty()) {
                $position = $allMunicipalities->search(fn ($candidate) => $candidate->getKey() === $municipality->getKey());
                Cache::put($this->cursorKey($providers), ($position + 1) % $allMunicipalities->count(), now()->addDays(14));
            }
        }
        foreach ($activeProviders as $provider) {
            $heartbeat->finished($provider, $providerTotals[$provider]);
            foreach ($totals as $key => $value) {
                $totals[$key] += $providerTotals[$provider][$key];
            }
        }

        $this->line(sprintf('Climate source refresh complete: Open-Meteo %d, CHIRPS %d, Sentinel-2 %d, skipped %d, failed %d.',
            $totals['open_meteo'], $totals['chirps'], $totals['sentinel2'], $totals['skipped'], $totals['failed']));

        return $totals['failed'] > 0 && $totals['open_meteo'] + $totals['chirps'] + $totals['sentinel2'] === 0
            ? self::FAILURE : self::SUCCESS;
    }

    /** @param Collection<int, Municipality> $municipalities */
    private function batch($municipalities, array $providers, array $ids)
    {
        if ($municipalities->count() <= 1) {
            return $municipalities;
        }
        $size = min(200, $municipalities->count(), max(1, (int) ($this->option('batch-size') ?: config('climate.refresh_batch_size', 100))));
        $cursor = $ids !== [] ? 0 : ((int) Cache::get($this->cursorKey($providers), 0)) % $municipalities->count();
        $selected = $municipalities->slice($cursor, $size);
        if ($selected->count() < $size) {
            $selected = $selected->concat($municipalities->slice(0, $size - $selected->count()));
        }

        return $selected->values();
    }

    private function cursorKey(array $providers): string
    {
        sort($providers);

        return 'climate.sources.cursor.'.hash('sha256', implode(',', $providers));
    }

    /** @return array<int, string> */
    private function providers(): array
    {
        $requested = collect($this->option('provider'))->map(fn ($provider) => strtolower(trim((string) $provider)))->unique()->values()->all();
        if ($requested !== []) {
            return array_diff($requested, ['open-meteo', 'chirps', 'sentinel2']) === [] ? $requested : [];
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
            $coverage = $this->forecastCoverage($daily);
            $fetchedAt = $forecast['fetched_at'] ?? null;
            if (! is_string($fetchedAt) || $fetchedAt === '') {
                $totals['failed']++;

                return;
            }
            $fetchedAt = CarbonImmutable::parse($fetchedAt);
            $recorder->municipality($municipality, 'open_meteo', 'forecast_7d', [
                'observed_on' => $fetchedAt->setTimezone((string) config('weather.timezone', 'Asia/Manila'))->toDateString(),
                'fetched_at' => $fetchedAt,
                'period_start' => $daily[0]['date'] ?? null,
                'period_end' => $daily[count($daily) - 1]['date'] ?? null,
                'value' => $coverage['coverage_complete'] ? array_sum(array_column($daily, 'precipitation_sum')) : null,
                'unit' => 'mm',
                'status' => $coverage['coverage_complete'] && ! ($forecast['is_stale'] ?? false) ? 'available' : 'partial',
                'source_label' => 'Open-Meteo seven-day forecast',
                'source_url' => 'https://open-meteo.com/en/docs',
                'metadata' => [
                    ...$coverage, 'is_stale' => (bool) ($forecast['is_stale'] ?? false),
                    'reference_point' => true,
                    'daily_rain_mm' => $coverage['coverage_complete'] ? array_sum(array_column($daily, 'precipitation_sum')) : null,
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
                // CHIRPS preliminary data has a normal reporting delay. Keep
                // that state visible without making the scheduler look broken.
                $recorder->municipality($municipality, 'chirps', 'rainfall_30d', [
                    'observed_on' => LocalTime::now()->toDateString(),
                    'status' => 'unavailable',
                    'source_label' => 'CHIRPS reference-area rainfall',
                    'source_url' => 'https://climateserv2.servirglobal.net/develop-api',
                    'metadata' => ['message' => $reading['status_message'] ?? 'CHIRPS has not returned a usable period yet.', 'coverage_complete' => false, 'reference_point' => true, 'dataset' => 'climateserv-chirps-0'],
                ]);
                $totals['skipped']++;

                return;
            }
            $recorder->municipality($municipality, 'chirps', 'rainfall_30d', [
                'observed_on' => $reading['observed_on'], 'period_start' => $reading['period_start'], 'period_end' => $reading['period_end'],
                'value' => $reading['rainfall_mm'], 'baseline_value' => $reading['baseline_mm'], 'anomaly_percent' => $reading['anomaly_percent'],
                'fetched_at' => $reading['fetched_at'],
                'unit' => 'mm', 'status' => ($reading['is_stale'] ?? false) ? 'partial' : 'available', 'source_label' => 'CHIRPS reference-area rainfall',
                'source_url' => $reading['source_url'],
                'metadata' => ['period_days' => 30, 'baseline_years' => $reading['baseline_years'], 'days_available' => $reading['days_available'],
                    'baseline_years_requested' => $reading['baseline_years_requested'], 'coverage_complete' => $reading['coverage_complete'],
                    'reference_point' => true, 'dataset' => $reading['dataset'], 'is_stale' => (bool) ($reading['is_stale'] ?? false), 'message' => $reading['status_message']],
            ]);
            $totals['chirps']++;
        } catch (Throwable $exception) {
            report($exception);
            $totals['failed']++;
        }
    }

    private function refreshSentinel(Municipality $municipality, SentinelImagery $sentinel, ParcelVegetationSnapshot $vegetation, ClimateRiskSourceRecorder $recorder, array &$totals): void
    {
        if ($this->satelliteRemaining <= 0 || ! config('climate.sources.sentinel2.enabled', false) || ! $sentinel->configured()) {
            return;
        }
        $from = CarbonImmutable::now('UTC')->subDays(31)->toDateString();
        $to = CarbonImmutable::now('UTC')->subDay()->toDateString();
        $cursorKey = 'climate.sources.sentinel-scan.v1.'.$municipality->getKey();
        $cursor = max(0, (int) Cache::get($cursorKey, 0));
        $wrapped = false;
        $examined = false;
        while ($this->satelliteScanRemaining > 0 && $this->satelliteRemaining > 0 && microtime(true) < $this->refreshDeadline) {
            $size = min(100, $this->satelliteScanRemaining);
            $plots = FarmPlot::query()->with('farmer.municipality')->whereHas('farmer', fn ($query) => $query->where('municipality_id', $municipality->getKey()))
                ->whereNotNull('polygon_json')->where('id', '>', $cursor)->orderBy('id')->limit($size)->get();
            if ($plots->isEmpty()) {
                Cache::put($cursorKey, 0, now()->addDays(14));
                if ($cursor === 0 || $wrapped || $examined) {
                    break;
                }
                $cursor = 0;
                $wrapped = true;

                continue;
            }
            $examined = true;
            $fresh = $this->option('force') ? [] : $this->freshSatelliteIds($plots);
            foreach ($plots as $plot) {
                if ($this->satelliteRemaining <= 0 || microtime(true) >= $this->refreshDeadline) {
                    return;
                }
                $this->satelliteScanRemaining--;
                $cursor = (int) $plot->id;
                Cache::put($cursorKey, $cursor, now()->addDays(14));
                if (isset($fresh[$plot->id])) {
                    continue;
                }
                $this->refreshSatellitePlot($plot, $sentinel, $vegetation, $recorder, $from, $to, $totals);
            }
            if ($plots->count() < $size) {
                Cache::put($cursorKey, 0, now()->addDays(14));
                break;
            }
        }
    }

    /** @return array<int, true> */
    private function freshSatelliteIds(Collection $plots): array
    {
        $fingerprints = $plots->mapWithKeys(fn (FarmPlot $plot) => [$plot->id => ClimateEvidenceContext::plot($plot)])->all();
        $readings = ClimateRiskSourceReading::query()->whereIn('farm_plot_id', $plots->modelKeys())
            ->where('provider', 'sentinel2')->where('metric', 'ndvi')
            ->where('fetched_at', '>=', now()->subHours(max(24, (int) config('climate.sources.sentinel2.refresh_hours', 168))))
            ->orderByDesc('fetched_at')->limit(5000)->get(['farm_plot_id', 'metadata']);
        $fresh = [];
        foreach ($readings as $reading) {
            if (isset($fingerprints[$reading->farm_plot_id]) && ($reading->metadata['context_fingerprint'] ?? null) === $fingerprints[$reading->farm_plot_id]) {
                $fresh[(int) $reading->farm_plot_id] = true;
            }
        }

        return $fresh;
    }

    private function refreshSatellitePlot(FarmPlot $plot, SentinelImagery $sentinel, ParcelVegetationSnapshot $vegetation, ClimateRiskSourceRecorder $recorder, string $from, string $to, array &$totals): void
    {
        try {
            $this->satelliteRemaining--;
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

    private function forecastCoverage(array $daily): array
    {
        $dates = [];
        $rainDays = 0;
        $temperatureDays = 0;
        foreach ($daily as $day) {
            if (! is_array($day)) {
                continue;
            }
            $date = $day['date'] ?? null;
            if (is_string($date) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                $dates[] = $date;
            }
            if (is_numeric($day['precipitation_sum'] ?? null) && is_finite((float) $day['precipitation_sum']) && (float) $day['precipitation_sum'] >= 0) {
                $rainDays++;
            }
            if (is_numeric($day['temperature_max'] ?? null) && is_finite((float) $day['temperature_max']) && (float) $day['temperature_max'] >= -90 && (float) $day['temperature_max'] <= 60) {
                $temperatureDays++;
            }
        }
        $consecutive = count($daily) === 7 && count(array_unique($dates)) === 7;
        if ($consecutive) {
            for ($i = 1; $i < 7; $i++) {
                $consecutive = $consecutive && CarbonImmutable::parse($dates[$i - 1])->addDay()->toDateString() === $dates[$i];
            }
        }

        return ['forecast_days' => count($daily), 'rain_days' => $rainDays, 'temperature_days' => $temperatureDays, 'coverage_complete' => $consecutive && $rainDays === 7];
    }

    private function fresh(Municipality $municipality, string $provider, string $metric): bool
    {
        if (! Schema::hasTable('climate_risk_source_readings')) {
            return false;
        }
        $hours = $provider === 'open_meteo'
            ? (int) config('climate.sources.open_meteo.refresh_hours', 6)
            : ($provider === 'chirps' ? (int) config('climate.sources.chirps.refresh_hours', 24) : (int) config('climate.sources.sentinel2.refresh_hours', 168));

        return ClimateRiskSourceReading::query()->where('municipality_id', $municipality->getKey())->where('provider', $provider)
            ->where('metric', $metric)->whereNull('farm_plot_id')
            ->where('metadata->context_fingerprint', ClimateEvidenceContext::municipality($municipality))
            ->where('fetched_at', '>=', now()->subHours(max(1, $hours)))->exists();
    }
}
