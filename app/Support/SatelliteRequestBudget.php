<?php

namespace App\Support;

use App\Exceptions\SatelliteUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

final class SatelliteRequestBudget
{
    public function reserve(): void
    {
        try {
            Cache::lock('sentinel:budget:lock', 10)->block(2, function (): void {
                $periods = $this->periods();
                foreach ($periods as $period) {
                    if ((int) Cache::get($period['key'], 0) >= $period['limit']) {
                        throw new SatelliteUnavailable('The local satellite request allowance has been reached. Ask the administrator to review usage.', 429);
                    }
                }
                foreach ($periods as $period) {
                    Cache::put($period['key'], (int) Cache::get($period['key'], 0) + 1, $period['reset_at']);
                }
            });
        } catch (LockTimeoutException $exception) {
            throw new SatelliteUnavailable('Satellite processing is busy. Retry shortly.');
        }
    }

    /** @return array<string, array{used: int, limit: int, remaining: int, percent: float, reset_at: string}> */
    public function snapshot(): array
    {
        $result = [];
        foreach ($this->periods() as $name => $period) {
            $used = max(0, (int) Cache::get($period['key'], 0));
            $limit = $period['limit'];
            $result[$name] = [
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'percent' => $limit > 0 ? min(100.0, round($used / $limit * 100, 1)) : 100.0,
                'reset_at' => $period['reset_at']->format('d M Y, H:i').' UTC',
            ];
        }

        return $result;
    }

    /** @return array<string, array{key: string, limit: int, reset_at: CarbonImmutable}> */
    private function periods(): array
    {
        $now = CarbonImmutable::now('UTC');

        return [
            'day' => ['key' => 'sentinel:requests:'.$now->format('Y-m-d'),
                'limit' => max(0, (int) config('sentinel.daily_requests', 100)),
                'reset_at' => $now->addDay()->startOfDay()],
            'month' => ['key' => 'sentinel:requests:'.$now->format('Y-m'),
                'limit' => max(0, (int) config('sentinel.monthly_requests', 2000)),
                'reset_at' => $now->addMonthNoOverflow()->startOfMonth()],
        ];
    }
}
