<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Aggregate infrastructure counters only: never store keys, URLs, geometry or identities. */
final class ProviderUsage
{
    public function __construct(private SatelliteRequestBudget $satelliteBudget, private SentinelImagery $satellite)
    {
    }

    public function visibleTo(User $user): bool
    {
        return ! $user->isVisitor() && ($user->isSystemOwner() || $user->isSuperAdmin())
            && $user->hasUsableScope();
    }

    public function forUser(User $user): ?array
    {
        if (! $this->visibleTo($user)) {
            return null;
        }

        $satellite = $this->read(fn () => $this->satelliteBudget->snapshot());
        $google = $this->read(function (): array {
            $result = [];
            foreach ($this->googlePeriods() as $name => $period) {
                $value = Cache::get($period['key']);
                $result[$name] = [
                    'used' => is_array($value) ? max(0, (int) ($value['used'] ?? 0)) : 0,
                    'started_at' => is_array($value) ? ($value['started_at'] ?? null) : null,
                ];
            }

            return $result;
        });

        return [
            'as_of' => CarbonImmutable::now('UTC')->format('d M Y, H:i').' UTC',
            'satellite' => [
                'configured' => $this->satellite->configured(),
                'periods' => $satellite,
            ],
            'google' => [
                'browser_configured' => trim((string) config('services.google_maps.key')) !== '',
                'static_configured' => trim((string) config('services.google_maps.static_key')) !== '',
                'periods' => $google,
            ],
        ];
    }

    /** Count outgoing attempts, including provider failures; cache hits never call this. */
    public function recordGoogleStaticRequest(): void
    {
        try {
            Cache::lock('provider-usage:google-static:lock', 5)->block(1, function (): void {
                $startedAt = CarbonImmutable::now('UTC')->format('d M Y, H:i').' UTC';
                foreach ($this->googlePeriods() as $period) {
                    $value = Cache::get($period['key']);
                    Cache::put($period['key'], [
                        'used' => (is_array($value) ? max(0, (int) ($value['used'] ?? 0)) : 0) + 1,
                        'started_at' => is_array($value) ? ($value['started_at'] ?? $startedAt) : $startedAt,
                    ], $period['reset_at']);
                }
            });
        } catch (Throwable $exception) {
            // Telemetry is best-effort and must not break an authorized image export.
            // Cache exception messages may contain connection credentials.
            Log::warning('Google Static Maps usage could not be recorded.');
        }
    }

    private function read(callable $read): ?array
    {
        try {
            return $read();
        } catch (Throwable $exception) {
            // Show unavailable rather than misleading zeroes; never log provider secrets.
            Log::warning('Provider usage counters could not be read.');

            return null;
        }
    }

    private function googlePeriods(): array
    {
        $now = CarbonImmutable::now('UTC');

        return [
            'day' => ['key' => 'provider-usage:google-static:'.$now->format('Y-m-d'),
                'reset_at' => $now->addDay()->startOfDay()],
            'month' => ['key' => 'provider-usage:google-static:'.$now->format('Y-m'),
                'reset_at' => $now->addMonthNoOverflow()->startOfMonth()],
        ];
    }
}
