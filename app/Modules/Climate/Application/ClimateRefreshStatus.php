<?php

namespace App\Modules\Climate\Application;

use Illuminate\Support\Facades\Cache;

/** Operational timestamps and aggregate counts only; never a claim that cron is installed. */
final class ClimateRefreshStatus
{
    private const PROVIDERS = ['open-meteo', 'chirps', 'sentinel2'];

    public function started(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return;
        }
        $prior = $this->read($provider) ?? [];
        Cache::put($this->key($provider), ['state' => 'running', 'started_at' => now()->utc()->toIso8601String(),
            'finished_at' => $prior['finished_at'] ?? null], now()->addDays(7));
    }

    public function finished(string $provider, array $totals): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return;
        }
        $prior = $this->read($provider) ?? [];
        $safe = [];
        foreach (['open_meteo', 'chirps', 'sentinel2', 'skipped', 'failed'] as $key) {
            $safe[$key] = max(0, (int) ($totals[$key] ?? 0));
        }
        Cache::put($this->key($provider), ['state' => $safe['failed'] > 0 ? 'needs_review' : 'finished',
            'started_at' => $prior['started_at'] ?? null, 'finished_at' => now()->utc()->toIso8601String(),
            'counts' => $safe], now()->addDays(7));
    }

    public function read(string $provider): ?array
    {
        $value = in_array($provider, self::PROVIDERS, true) ? Cache::get($this->key($provider)) : null;

        return is_array($value) ? $value : null;
    }

    private function key(string $provider): string
    {
        return 'climate:refresh-status:v1:'.$provider;
    }
}
