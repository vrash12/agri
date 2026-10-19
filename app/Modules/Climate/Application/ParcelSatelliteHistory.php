<?php

namespace App\Modules\Climate\Application;

use App\Models\FarmPlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Bounded summaries of completed checks. Callers must authorize the parcel first. */
final class ParcelSatelliteHistory
{
    public function remember(FarmPlot $plot, array $result): void
    {
        // Every operation is optional: even a cache/backend failure must leave the accepted check usable.
        try {
            $index = $result['index'] ?? '';
            if (! in_array($index, SatelliteIndex::IDS, true) || ! is_array($result['observations'] ?? null)
                || count($result['observations']) > 31) {
                return;
            }
            $definition = SatelliteIndex::definition($index);
            $cloud = $result['scene_cloud_limit'] ?? null;
            if (! is_int($cloud) || $cloud < 0 || $cloud > 100 || ($result['resolution_m'] ?? null) !== $definition['resolution_m']) {
                return;
            }
            $key = $this->key($plot);
            $today = CarbonImmutable::now('UTC');
            $series = $this->series($key, $index, $cloud);
            $rows = [];
            foreach ($result['observations'] as $observation) {
                if (! is_array($observation) || ($observation['status'] ?? '') !== 'available') {
                    continue;
                }
                $row = $this->validateRow([
                    'date' => $observation['date'] ?? null, 'mean' => $observation['mean'] ?? null,
                    'valid_pixels' => $observation['valid_pixels'] ?? null,
                    'checked_at' => $today->toIso8601String(), 'series_key' => $series,
                    'grid_metres' => $definition['resolution_m'], 'scene_cloud_limit' => $cloud,
                ], $key, $index, $today);
                if ($row !== null) {
                    $rows[$row['date']] = $row;
                }
            }
            if ($rows === []) {
                return;
            }
            Cache::lock($key.':write', 10)->block(1, function () use ($key, $index, $rows, $series, $today): void {
                $history = $this->validateHistory(Cache::get($key, []), $key, $today);
                // A settings change starts a fresh comparable series instead of mixing scene filters.
                $existing = array_filter($history[$index], static fn (array $row): bool => $row['series_key'] === $series);
                $merged = array_column($existing, null, 'date');
                foreach ($rows as $date => $row) {
                    $merged[$date] = $row;
                }
                ksort($merged);
                $history[$index] = array_values(array_slice($merged, -90, null, true));
                Cache::put($key, $history, now()->addDays(90));
            });
        } catch (Throwable) {
            // No provider retry is triggered by a history-cache failure.
        }
    }

    public function forPlot(FarmPlot $plot): array
    {
        try {
            $key = $this->key($plot);

            return $this->validateHistory(Cache::get($key, []), $key, CarbonImmutable::now('UTC'));
        } catch (Throwable) {
            return array_fill_keys(SatelliteIndex::IDS, []);
        }
    }

    private function validateHistory(mixed $history, string $key, CarbonImmutable $today): array
    {
        $output = array_fill_keys(SatelliteIndex::IDS, []);
        foreach (SatelliteIndex::IDS as $index) {
            $stored = is_array($history) && is_array($history[$index] ?? null) ? $history[$index] : [];
            $rows = [];
            foreach (array_slice($stored, -90) as $candidate) {
                $row = $this->validateRow($candidate, $key, $index, $today);
                if ($row !== null) {
                    $date = $row['date'];
                    if (! isset($rows[$date]) || strcmp($row['checked_at'], $rows[$date]['checked_at']) >= 0) {
                        $rows[$date] = $row;
                    }
                }
            }
            ksort($rows);
            $output[$index] = array_values($rows);
        }

        return $output;
    }

    private function validateRow(mixed $row, string $key, string $index, CarbonImmutable $today): ?array
    {
        if (! is_array($row)) {
            return null;
        }
        $date = $row['date'] ?? null;
        $parsed = ParcelSuitability::date($date);
        $definition = SatelliteIndex::definition($index);
        $mean = ParcelSuitability::number($row['mean'] ?? null, $definition['minimum'], $definition['maximum']);
        $pixels = $row['valid_pixels'] ?? null;
        $cloud = $row['scene_cloud_limit'] ?? null;
        $series = $row['series_key'] ?? null;
        $checked = $row['checked_at'] ?? null;
        if (! $parsed || $date < $today->subDays(90)->toDateString() || $date > $today->toDateString()
            || $mean === null || ! is_int($pixels) || $pixels < 1 || $pixels > 300000
            || ! is_int($cloud) || $cloud < 0 || $cloud > 100
            || ($row['grid_metres'] ?? null) !== $definition['resolution_m']
            || ! is_string($series) || ! hash_equals($this->series($key, $index, $cloud), $series)
            || ! is_string($checked) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\+00:00|Z)$/D', $checked)) {
            return null;
        }
        $checked = str_ends_with($checked, 'Z') ? substr($checked, 0, -1).'+00:00' : $checked;
        try {
            $checkedAt = CarbonImmutable::createFromFormat(DATE_ATOM, $checked);
            if ($checkedAt->format(DATE_ATOM) !== $checked || $checkedAt->gt($today->addMinutes(5)) || $checkedAt->lt($parsed)) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        // Rebuild the allowlisted shape: stale flags and arbitrary cached fields are never trusted.
        return ['date' => $date, 'mean' => $mean, 'valid_pixels' => $pixels,
            'checked_at' => $checkedAt->toIso8601String(), 'series_key' => $series,
            'source' => 'Copernicus Sentinel-2 Level-2A', 'grid_metres' => $definition['resolution_m'],
            'scene_cloud_limit' => $cloud,
            'is_stale' => $date < $today->subDays((int) config('agronomy.freshness_days.satellite', 14))->toDateString()];
    }

    private function series(string $key, string $index, int $cloud): string
    {
        return hash('sha256', $key.'|'.$index.'|'.$cloud);
    }

    private function key(FarmPlot $plot): string
    {
        return 'climate:satellite-history:v1:'.hash('sha256', json_encode([
            ClimateEvidenceContext::plot($plot), config('sentinel.enabled'),
            hash('sha256', config('sentinel.client_id').'|'.config('sentinel.client_secret')),
            hash_file('sha256', __DIR__.'/SentinelImagery.php'),
            hash_file('sha256', __DIR__.'/SentinelParcel.php'),
            hash_file('sha256', app_path('Support/GeoGeometry.php')),
            array_map(static fn (string $index): array => [
                'resolution_m' => SatelliteIndex::definition($index)['resolution_m'],
                'input' => SatelliteIndex::input($index), 'calculation' => SatelliteIndex::calculation($index),
            ], SatelliteIndex::IDS),
        ], JSON_THROW_ON_ERROR));
    }
}
