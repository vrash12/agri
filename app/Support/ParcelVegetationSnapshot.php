<?php

namespace App\Support;

use App\Models\FarmPlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** A bounded read-through summary of accepted satellite checks; never calls a provider. */
final class ParcelVegetationSnapshot
{
    public function remember(FarmPlot $plot, array $result): void
    {
        if (! is_array($result['observations'] ?? null) || count($result['observations']) > 31) {
            return;
        }
        $rows = array_filter($result['observations'] ?? [], function ($row): bool {
            return is_array($row) && ($row['status'] ?? '') === 'available' && ($row['valid_pixels'] ?? 0) > 0
                && is_numeric($row['mean'] ?? null) && is_finite((float) $row['mean']) && abs((float) $row['mean']) <= 1
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'] ?? '')
                && checkdate((int) substr($row['date'], 5, 2), (int) substr($row['date'], 8, 2), (int) substr($row['date'], 0, 4))
                && ($row['date'] ?? '') <= now()->utc()->toDateString();
        });
        usort($rows, fn (array $a, array $b) => strcmp($b['date'], $a['date']));
        if ($rows === []) {
            return;
        }
        $latest = $rows[0];
        $key = $this->key($plot);
        // This summary is optional. A concurrent check may own the cache lock;
        // skip our summary write rather than delay or fail the satellite reply.
        Cache::lock($key.':write', 10)->get(function () use ($plot, $latest, $key): void {
            $previous = $this->forPlot($plot);
            if ($previous && $previous['observed_on'] > $latest['date']) {
                return;
            }
            Cache::put($key, [
                'mean' => (float) $latest['mean'], 'valid_pixels' => (int) $latest['valid_pixels'],
                'observed_on' => $latest['date'], 'checked_at' => now()->utc()->toIso8601String(),
                'source' => 'Copernicus Sentinel-2 Level-2A',
            ], now()->addDays(31));
        });
    }

    public function forPlot(FarmPlot $plot): ?array
    {
        if (! is_array($plot->polygon_json)) {
            return null;
        }
        $row = Cache::get($this->key($plot));
        if (! is_array($row)) {
            return null;
        }
        $row['is_stale'] = CarbonImmutable::parse($row['observed_on'], 'UTC')->lt(now()->utc()->subDays(14)->startOfDay());

        return $row;
    }

    private function key(FarmPlot $plot): string
    {
        // Changes to parcel ownership or any boundary point invalidate the summary.
        $frame = hash('sha256', json_encode([$plot->farmer_id, $plot->farmer?->municipality_id, $plot->polygon_json], JSON_THROW_ON_ERROR));

        return 'climate:vegetation:v1:'.$plot->id.':'.$frame;
    }
}
