<?php

namespace App\Support;

use App\Models\ClimateRiskSourceReading;
use App\Models\FarmPlot;
use App\Models\Municipality;
use Illuminate\Support\Facades\Schema;

/**
 * Stores bounded provider summaries without turning them into office findings.
 * Provider payloads are deliberately reduced before they reach the database.
 */
final class ClimateRiskSourceRecorder
{
    public function installed(): bool
    {
        return Schema::hasTable('climate_risk_source_readings');
    }

    /** @param array<string, mixed> $reading */
    public function municipality(Municipality $municipality, string $provider, string $metric, array $reading): ?ClimateRiskSourceReading
    {
        return $this->save($municipality, null, $provider, $metric, $reading);
    }

    /** @param array<string, mixed> $reading */
    public function plot(FarmPlot $plot, string $provider, string $metric, array $reading): ?ClimateRiskSourceReading
    {
        $plot->loadMissing('farmer');
        $municipality = $plot->farmer?->municipality;
        if (! $municipality) {
            return null;
        }

        return $this->save($municipality, $plot, $provider, $metric, $reading);
    }

    /** @param array<string, mixed> $reading */
    private function save(Municipality $municipality, ?FarmPlot $plot, string $provider, string $metric, array $reading): ?ClimateRiskSourceReading
    {
        if (! $this->installed() || ! isset($reading['observed_on'], $reading['source_label'], $reading['source_url'])) {
            return null;
        }

        $observedOn = (string) $reading['observed_on'];
        $sourceKey = hash('sha256', implode('|', [
            $municipality->getKey(), $plot?->getKey() ?? 'municipality', $provider, $metric, $observedOn,
        ]));
        $metadata = is_array($reading['metadata'] ?? null) ? $this->metadata($reading['metadata']) : null;

        return ClimateRiskSourceReading::query()->updateOrCreate(
            ['source_key' => $sourceKey],
            [
                'municipality_id' => $municipality->getKey(),
                'farm_plot_id' => $plot?->getKey(),
                'provider' => $provider,
                'metric' => $metric,
                'observed_on' => $observedOn,
                'period_start' => $reading['period_start'] ?? null,
                'period_end' => $reading['period_end'] ?? null,
                'value' => $this->number($reading['value'] ?? null),
                'baseline_value' => $this->number($reading['baseline_value'] ?? null),
                'anomaly_percent' => $this->number($reading['anomaly_percent'] ?? null, 2),
                'unit' => isset($reading['unit']) ? substr((string) $reading['unit'], 0, 24) : null,
                'status' => in_array($reading['status'] ?? 'available', ['available', 'partial', 'unavailable'], true)
                    ? $reading['status'] : 'unavailable',
                'source_label' => substr((string) $reading['source_label'], 0, 180),
                'source_url' => substr((string) $reading['source_url'], 0, 500),
                'metadata' => $metadata,
                'fetched_at' => $reading['fetched_at'] ?? now()->utc(),
            ]
        );
    }

    /** @param array<string, mixed> $metadata */
    private function metadata(array $metadata): array
    {
        $allowed = ['forecast_days', 'daily_rain_mm', 'temperature_max_c', 'rain_probability', 'period_days', 'baseline_years', 'days_available', 'is_stale', 'message'];
        $safe = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $metadata) && (is_scalar($metadata[$key]) || $metadata[$key] === null)) {
                $safe[$key] = $metadata[$key];
            }
        }

        return $safe;
    }

    private function number($value, int $precision = 4): ?float
    {
        return is_numeric($value) && is_finite((float) $value)
            ? round((float) $value, $precision) : null;
    }
}
