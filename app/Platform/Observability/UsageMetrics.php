<?php

namespace App\Platform\Observability;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Bounded aggregates, never SQL, bindings, URLs, record IDs or conversation text. */
final class UsageMetrics
{
    public const PROVIDERS = ['sentinel-statistics', 'sentinel-image', 'sentinel-auth', 'google-static', 'open-meteo', 'chirps', 'openai'];

    public const CACHES = ['sentinel-statistics', 'sentinel-image', 'weather', 'chirps', 'assistant-help', 'google-static'];

    public const GROUPS = ['dashboard', 'farmers', 'land', 'satellite', 'climate', 'assistant', 'agriculture', 'other'];

    public const MAPS = ['farmers-3d', 'crop-editor-2d', 'satellite-2d'];

    private array $pending = [];

    private bool $flushing = false;

    private bool $observing = false;

    private array $queries = [];

    public function beginRequest(): void
    {
        $this->queries = ['count' => 0, 'ms' => 0, 'slow' => 0, 'max_ms' => 0];
        $this->observing = true;
    }

    public function query(float $milliseconds): void
    {
        if (! $this->observing || $this->flushing || ! $this->enabled() || ! is_finite($milliseconds)) {
            return;
        }
        $milliseconds = max(0, $milliseconds);
        $this->queries['count']++;
        $this->queries['ms'] += $milliseconds;
        $this->queries['max_ms'] = max($this->queries['max_ms'], $milliseconds);
        $this->queries['slow'] += (int) ($milliseconds >= max(1, (int) config('observability.slow_query_ms', 200)));
    }

    public function response(string $route, float $milliseconds, int $status): void
    {
        $this->observing = false;
        $group = $this->group($route);
        $prefix = 'http.'.$group.'.';
        $this->add($prefix.'count');
        $this->add($prefix.'errors', (int) ($status >= 500));
        $this->duration($prefix, $milliseconds);
        $this->add($prefix.'slow', (int) ($milliseconds >= max(1, (int) config('observability.slow_response_ms', 1000))));
        foreach ($this->queries as $key => $value) {
            $this->add('db.'.$group.'.'.$key, $value, $key === 'max_ms');
        }
    }

    public function providerAttempt(string $provider): void
    {
        if (in_array($provider, self::PROVIDERS, true)) {
            $this->add('provider.'.$provider.'.attempts');
        }
    }

    public function providerFinished(string $provider, float $milliseconds, bool $failed): void
    {
        if (in_array($provider, self::PROVIDERS, true)) {
            $prefix = 'provider.'.$provider.'.';
            $this->add($prefix.'completed');
            $this->add($prefix.'failed', (int) $failed);
            $this->duration($prefix, $milliseconds);
        }
    }

    public function cache(string $cache, bool $hit): void
    {
        if (in_array($cache, self::CACHES, true)) {
            $this->add('cache.'.$cache.'.'.($hit ? 'hits' : 'misses'));
        }
    }

    public function tokens(int $input, int $output): void
    {
        $this->add('ai.input_tokens', max(0, $input));
        $this->add('ai.output_tokens', max(0, $output));
        $this->add('ai.responses');
    }

    public function map(string $map, string $event): void
    {
        if (in_array($map, self::MAPS, true) && in_array($event, ['created', 'reused'], true)) {
            $this->add('map.'.$map.'.'.$event);
        }
    }

    /** Merge once per request/command, without waiting for a busy telemetry lock. */
    public function flush(): void
    {
        if ($this->flushing || $this->pending === [] || ! $this->enabled()) {
            return;
        }
        $pending = $this->pending;
        $this->pending = [];
        $this->flushing = true;
        try {
            foreach ($this->periods() as $period) {
                Cache::lock($period['key'].':lock', 5)->get(function () use ($period, $pending): void {
                    $stored = Cache::get($period['key'], []);
                    $stored = is_array($stored) ? $stored : [];
                    foreach ($pending as $key => $value) {
                        $previous = max(0, (float) ($stored[$key] ?? 0));
                        $stored[$key] = str_ends_with($key, '.max_ms') ? max($previous, $value) : $previous + $value;
                    }
                    $stored['started_at'] ??= CarbonImmutable::now('UTC')->toIso8601String();
                    Cache::put($period['key'], $stored, $period['expires']);
                });
            }
        } catch (Throwable) {
            // Telemetry must never fail a request or log sensitive cache exceptions.
        } finally {
            $this->flushing = false;
        }
    }

    public function snapshot(): array
    {
        if (! $this->enabled()) {
            return ['enabled' => false, 'periods' => null];
        }
        try {
            $periods = [];
            foreach ($this->periods() as $name => $period) {
                $stored = Cache::get($period['key'], []);
                $periods[$name] = is_array($stored) ? $stored : [];
            }

            return ['enabled' => true, 'periods' => $periods];
        } catch (Throwable) {
            return ['enabled' => true, 'periods' => null];
        }
    }

    private function enabled(): bool
    {
        return (bool) config('observability.enabled', true);
    }

    private function add(string $key, float $value = 1, bool $maximum = false): void
    {
        if (! $this->enabled() || ! is_finite($value)) {
            return;
        }
        $value = max(0, $value);
        $this->pending[$key] = $maximum ? max($this->pending[$key] ?? 0, $value) : ($this->pending[$key] ?? 0) + $value;
    }

    private function duration(string $prefix, float $milliseconds): void
    {
        $this->add($prefix.'ms', $milliseconds);
        $this->add($prefix.'max_ms', $milliseconds, true);
    }

    private function group(string $route): string
    {
        return match (true) {
            $route === 'dashboard' => 'dashboard',
            str_starts_with($route, 'farm-plots.satellite.') => 'satellite',
            str_starts_with($route, 'farm-plots.'), str_starts_with($route, 'municipality-boundaries.') => 'land',
            str_starts_with($route, 'farmers.') => 'farmers',
            str_starts_with($route, 'climate-risk.') => 'climate',
            $route === 'assistant.message' => 'assistant',
            str_starts_with($route, 'harvest-records.'), str_starts_with($route, 'rice-seed-distributions.') => 'agriculture',
            default => 'other',
        };
    }

    private function periods(): array
    {
        $now = CarbonImmutable::now('UTC');

        return [
            'day' => ['key' => 'usage-metrics:v1:day:'.$now->format('Y-m-d'), 'expires' => $now->addDays(2)->startOfDay()],
            'month' => ['key' => 'usage-metrics:v1:month:'.$now->format('Y-m'), 'expires' => $now->addMonthNoOverflow()->startOfMonth()->addDays(2)],
        ];
    }
}
