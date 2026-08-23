<?php

namespace App\Services;

use App\Models\Municipality;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WeatherForecastService
{
    /**
     * Return a normalized, municipality-level forecast and farm guidance.
     *
     * @return array<string, mixed>
     */
    public function forMunicipality(
        Municipality $municipality,
        bool $forceRefresh = false
    ): array {
        $coordinates = $this->coordinatesFor($municipality->name);

        if ($coordinates === null) {
            return $this->unavailable(
                $municipality,
                'No forecast reference point is configured for this municipality.'
            );
        }

        $cacheKey = 'weather.forecast.v2.'.$municipality->getKey();
        $staleKey = $cacheKey.'.last-known';

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $refreshLock = Cache::lock(
            $cacheKey.'.refresh-lock',
            max(10, (int) config('weather.refresh_lock_seconds', 30))
        );
        $hasRefreshLock = false;

        try {
            $hasRefreshLock = (bool) $refreshLock->block(
                max(1, (int) config('weather.refresh_wait_seconds', 3))
            );

            // A different request may have populated the cache while this
            // request waited. Reuse it instead of calling the provider again.
            if (! $forceRefresh) {
                $cached = Cache::get($cacheKey);

                if (is_array($cached)) {
                    return $cached;
                }
            }

            $response = Http::acceptJson()
                ->timeout(max(2, (int) config('weather.timeout_seconds', 8)))
                ->retry(2, 250)
                ->get((string) config('weather.forecast_url'), [
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude'],
                    'timezone' => (string) config('weather.timezone', 'Asia/Manila'),
                    'forecast_days' => 7,
                    'current' => implode(',', [
                        'temperature_2m',
                        'relative_humidity_2m',
                        'apparent_temperature',
                        'precipitation',
                        'weather_code',
                        'wind_speed_10m',
                        'wind_gusts_10m',
                    ]),
                    'hourly' => implode(',', [
                        'temperature_2m',
                        'relative_humidity_2m',
                        'precipitation_probability',
                        'precipitation',
                        'wind_gusts_10m',
                        'soil_moisture_0_to_1cm',
                    ]),
                    'daily' => implode(',', [
                        'weather_code',
                        'temperature_2m_max',
                        'temperature_2m_min',
                        'precipitation_sum',
                        'precipitation_probability_max',
                        'wind_speed_10m_max',
                        'wind_gusts_10m_max',
                        'et0_fao_evapotranspiration',
                        'sunrise',
                        'sunset',
                    ]),
                ])
                ->throw();

            $forecast = $this->normalize(
                $municipality,
                $coordinates,
                $response->json()
            );

            Cache::put(
                $cacheKey,
                $forecast,
                now()->addMinutes(max(5, (int) config('weather.cache_minutes', 30)))
            );
            Cache::put(
                $staleKey,
                $forecast,
                now()->addHours(max(1, (int) config('weather.stale_hours', 12)))
            );

            return $forecast;
        } catch (LockTimeoutException $exception) {
            $stale = Cache::get($staleKey);

            if (is_array($stale)) {
                $stale['is_stale'] = true;
                $stale['status_message'] = 'A forecast refresh is already running. Showing the most recent cached forecast.';

                return $stale;
            }

            return $this->unavailable(
                $municipality,
                'A forecast refresh is already running. Please retry shortly.'
            );
        } catch (Throwable $exception) {
            report($exception);

            $stale = Cache::get($staleKey);

            if (is_array($stale)) {
                $stale['is_stale'] = true;
                $stale['status_message'] = 'The live provider is temporarily unavailable. Showing the most recent cached forecast.';

                return $stale;
            }

            return $this->unavailable(
                $municipality,
                'The forecast provider is temporarily unavailable. Check PAGASA for official weather information.'
            );
        } finally {
            if ($hasRefreshLock) {
                $refreshLock->release();
            }
        }
    }

    /** @return array{latitude: float, longitude: float}|null */
    private function coordinatesFor(?string $name): ?array
    {
        $key = Str::of((string) $name)
            ->ascii()
            ->lower()
            ->replaceMatches('/\bmunicipality\b/', '')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
        $coordinates = config('weather.municipalities.'.$key);

        if (! is_array($coordinates)) {
            return null;
        }

        return [
            'latitude' => (float) $coordinates['latitude'],
            'longitude' => (float) $coordinates['longitude'],
        ];
    }

    /**
     * @param  array{latitude: float, longitude: float}  $coordinates
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(
        Municipality $municipality,
        array $coordinates,
        array $payload
    ): array {
        $dailyPayload = (array) Arr::get($payload, 'daily', []);
        $dailyTimes = (array) ($dailyPayload['time'] ?? []);
        $daily = [];

        foreach ($dailyTimes as $index => $date) {
            $code = (int) $this->at($dailyPayload, 'weather_code', $index, -1);
            $daily[] = [
                'date' => (string) $date,
                'day_label' => $this->dateLabel((string) $date),
                'weather_code' => $code,
                'condition' => $this->weatherLabel($code),
                'condition_icon' => $this->weatherIcon($code),
                'temperature_max' => $this->number($this->at($dailyPayload, 'temperature_2m_max', $index)),
                'temperature_min' => $this->number($this->at($dailyPayload, 'temperature_2m_min', $index)),
                'precipitation_sum' => $this->number($this->at($dailyPayload, 'precipitation_sum', $index)),
                'precipitation_probability' => $this->integer($this->at($dailyPayload, 'precipitation_probability_max', $index)),
                'wind_speed_max' => $this->number($this->at($dailyPayload, 'wind_speed_10m_max', $index)),
                'wind_gust_max' => $this->number($this->at($dailyPayload, 'wind_gusts_10m_max', $index)),
                'et0' => $this->number($this->at($dailyPayload, 'et0_fao_evapotranspiration', $index)),
                'sunrise' => $this->timeLabel($this->at($dailyPayload, 'sunrise', $index)),
                'sunset' => $this->timeLabel($this->at($dailyPayload, 'sunset', $index)),
            ];
        }

        $hourlyPayload = (array) Arr::get($payload, 'hourly', []);
        $hourlyTimes = (array) ($hourlyPayload['time'] ?? []);
        $hourly = [];
        $now = CarbonImmutable::now((string) config('weather.timezone', 'Asia/Manila'));

        foreach ($hourlyTimes as $index => $time) {
            try {
                $moment = CarbonImmutable::parse(
                    (string) $time,
                    (string) config('weather.timezone', 'Asia/Manila')
                );
            } catch (Throwable $exception) {
                continue;
            }

            if ($moment->lt($now->startOfHour()) || $moment->gt($now->addHours(24))) {
                continue;
            }

            $hourly[] = [
                'time' => $moment->toIso8601String(),
                'label' => $moment->format('D g A'),
                'temperature' => $this->number($this->at($hourlyPayload, 'temperature_2m', $index)),
                'humidity' => $this->integer($this->at($hourlyPayload, 'relative_humidity_2m', $index)),
                'precipitation_probability' => $this->integer($this->at($hourlyPayload, 'precipitation_probability', $index)),
                'precipitation' => $this->number($this->at($hourlyPayload, 'precipitation', $index)),
                'wind_gust' => $this->number($this->at($hourlyPayload, 'wind_gusts_10m', $index)),
                'soil_moisture' => $this->number($this->at($hourlyPayload, 'soil_moisture_0_to_1cm', $index), 3),
            ];
        }

        $currentPayload = (array) Arr::get($payload, 'current', []);
        $currentCode = (int) ($currentPayload['weather_code'] ?? -1);
        $current = [
            'time' => (string) ($currentPayload['time'] ?? ''),
            'temperature' => $this->number($currentPayload['temperature_2m'] ?? null),
            'apparent_temperature' => $this->number($currentPayload['apparent_temperature'] ?? null),
            'humidity' => $this->integer($currentPayload['relative_humidity_2m'] ?? null),
            'precipitation' => $this->number($currentPayload['precipitation'] ?? null),
            'wind_speed' => $this->number($currentPayload['wind_speed_10m'] ?? null),
            'wind_gust' => $this->number($currentPayload['wind_gusts_10m'] ?? null),
            'weather_code' => $currentCode,
            'condition' => $this->weatherLabel($currentCode),
            'condition_icon' => $this->weatherIcon($currentCode),
        ];

        $summary = $this->summary($daily);

        return [
            'available' => true,
            'is_stale' => false,
            'status_message' => null,
            'municipality' => [
                'id' => (int) $municipality->getKey(),
                'name' => (string) $municipality->name,
            ],
            'coordinates' => $coordinates,
            'timezone' => (string) ($payload['timezone'] ?? config('weather.timezone')),
            'fetched_at' => now()->toIso8601String(),
            'provider' => 'Open-Meteo',
            'provider_url' => 'https://open-meteo.com/',
            'current' => $current,
            'daily' => $daily,
            'hourly' => $hourly,
            'summary' => $summary,
            'advisories' => $this->advisories($daily, $summary),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $daily
     * @return array<string, float|int|null>
     */
    private function summary(array $daily): array
    {
        $nextThreeDays = array_slice($daily, 0, 3);
        $values = fn (string $key, ?array $days = null): array => array_values(
            array_filter(
                array_map(fn (array $day) => $day[$key] ?? null, $days ?? $daily),
                fn ($value) => $value !== null
            )
        );
        $rain = $values('precipitation_sum');
        $threeDayRain = $values('precipitation_sum', $nextThreeDays);
        $rainProbability = $values('precipitation_probability');
        $temperatures = $values('temperature_max');
        $gusts = $values('wind_gust_max');
        $et0 = $values('et0', $nextThreeDays);

        return [
            'seven_day_rain' => round(array_sum($rain), 1),
            'three_day_rain' => round(array_sum($threeDayRain), 1),
            'maximum_daily_rain' => $rain === [] ? null : max($rain),
            'maximum_rain_probability' => $rainProbability === [] ? null : max($rainProbability),
            'maximum_temperature' => $temperatures === [] ? null : max($temperatures),
            'maximum_wind_gust' => $gusts === [] ? null : max($gusts),
            'maximum_three_day_et0' => $et0 === [] ? null : max($et0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $daily
     * @param  array<string, float|int|null>  $summary
     * @return array<int, array<string, string>>
     */
    private function advisories(array $daily, array $summary): array
    {
        $thresholds = (array) config('weather.thresholds', []);
        $advisories = [];
        $maximumRain = (float) ($summary['maximum_daily_rain'] ?? 0);
        $maximumRainProbability = (int) ($summary['maximum_rain_probability'] ?? 0);
        $maximumGust = (float) ($summary['maximum_wind_gust'] ?? 0);
        $maximumTemperature = (float) ($summary['maximum_temperature'] ?? 0);
        $threeDayRain = (float) ($summary['three_day_rain'] ?? 0);
        $maximumEt0 = (float) ($summary['maximum_three_day_et0'] ?? 0);

        if ($maximumRain >= (float) ($thresholds['heavy_rain_mm'] ?? 50)) {
            $advisories[] = [
                'severity' => 'high',
                'category' => 'Rainfall risk',
                'title' => 'Heavy rainfall may affect fields',
                'message' => 'Inspect drainage, secure movable inputs and machinery, and verify official PAGASA rainfall or flood warnings before field deployment.',
                'metric' => number_format($maximumRain, 1).' mm maximum daily rain',
            ];
        } elseif ($maximumRain >= (float) ($thresholds['moderate_rain_mm'] ?? 25)) {
            $advisories[] = [
                'severity' => 'moderate',
                'category' => 'Rainfall watch',
                'title' => 'Wet-field disruption is possible',
                'message' => 'Review drainage and avoid scheduling spraying, drying, or harvest activity during the wettest forecast period.',
                'metric' => number_format($maximumRain, 1).' mm maximum daily rain',
            ];
        }

        if ($maximumGust >= (float) ($thresholds['strong_gust_kmh'] ?? 62)) {
            $advisories[] = [
                'severity' => 'high',
                'category' => 'Wind risk',
                'title' => 'Strong gusts could damage crops or structures',
                'message' => 'Secure nursery covers, trellises, tools, and light equipment. Check PAGASA bulletins before outdoor operations.',
                'metric' => number_format($maximumGust, 0).' km/h forecast gust',
            ];
        } elseif ($maximumGust >= (float) ($thresholds['moderate_gust_kmh'] ?? 40)) {
            $advisories[] = [
                'severity' => 'moderate',
                'category' => 'Wind watch',
                'title' => 'Breezy field conditions expected',
                'message' => 'Delay pesticide spraying when wind is elevated and inspect exposed farm structures.',
                'metric' => number_format($maximumGust, 0).' km/h forecast gust',
            ];
        }

        if ($maximumTemperature >= (float) ($thresholds['heat_celsius'] ?? 35)) {
            $advisories[] = [
                'severity' => 'moderate',
                'category' => 'Heat',
                'title' => 'Heat precautions are recommended',
                'message' => 'Schedule strenuous work earlier, provide drinking water and rest breaks, and monitor livestock and young plants for heat stress.',
                'metric' => number_format($maximumTemperature, 1).'°C forecast maximum',
            ];
        }

        if ($maximumRainProbability >= (int) ($thresholds['fieldwork_rain_probability'] ?? 70)) {
            $advisories[] = [
                'severity' => 'info',
                'category' => 'Field operations',
                'title' => 'Plan weather-sensitive work carefully',
                'message' => 'Use the hourly forecast before spraying, fertilizer application, crop drying, or machinery dispatch.',
                'metric' => $maximumRainProbability.'% peak rain probability',
            ];
        }

        if (
            $daily !== []
            && $threeDayRain <= (float) ($thresholds['dry_three_day_rain_mm'] ?? 3)
            && $maximumEt0 >= (float) ($thresholds['high_et0_mm'] ?? 4)
        ) {
            $advisories[] = [
                'severity' => 'info',
                'category' => 'Water management',
                'title' => 'Review irrigation needs',
                'message' => 'Low rainfall and elevated reference evapotranspiration may increase crop water demand. Check actual soil moisture before irrigating.',
                'metric' => number_format($threeDayRain, 1).' mm rain over 3 days',
            ];
        }

        if ($advisories === []) {
            $advisories[] = [
                'severity' => 'normal',
                'category' => 'Operations',
                'title' => 'No threshold-based concerns detected',
                'message' => 'Continue routine monitoring and check PAGASA before making safety-critical field decisions.',
                'metric' => 'Forecast guidance only',
            ];
        }

        return $advisories;
    }

    /** @return array<string, mixed> */
    private function unavailable(Municipality $municipality, string $message): array
    {
        return [
            'available' => false,
            'is_stale' => false,
            'status_message' => $message,
            'municipality' => [
                'id' => (int) $municipality->getKey(),
                'name' => (string) $municipality->name,
            ],
            'coordinates' => null,
            'timezone' => (string) config('weather.timezone', 'Asia/Manila'),
            'fetched_at' => null,
            'provider' => 'Open-Meteo',
            'provider_url' => 'https://open-meteo.com/',
            'current' => [],
            'daily' => [],
            'hourly' => [],
            'summary' => [],
            'advisories' => [],
        ];
    }

    private function at(array $payload, string $key, int $index, $default = null)
    {
        $values = $payload[$key] ?? [];

        return is_array($values) ? ($values[$index] ?? $default) : $default;
    }

    private function number($value, int $precision = 1): ?float
    {
        return is_numeric($value) ? round((float) $value, $precision) : null;
    }

    private function integer($value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function dateLabel(string $date): string
    {
        try {
            return CarbonImmutable::parse($date)->format('D, M j');
        } catch (Throwable $exception) {
            return $date;
        }
    }

    private function timeLabel($value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('g:i A');
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function weatherLabel(int $code): string
    {
        if ($code === 0) {
            return 'Clear sky';
        }
        if (in_array($code, [1, 2], true)) {
            return 'Partly cloudy';
        }
        if ($code === 3) {
            return 'Overcast';
        }
        if (in_array($code, [45, 48], true)) {
            return 'Foggy';
        }
        if (in_array($code, [51, 53, 55, 56, 57], true)) {
            return 'Drizzle';
        }
        if (in_array($code, [61, 63, 65, 66, 67], true)) {
            return 'Rain';
        }
        if (in_array($code, [71, 73, 75, 77, 85, 86], true)) {
            return 'Wintry precipitation';
        }
        if (in_array($code, [80, 81, 82], true)) {
            return 'Rain showers';
        }
        if (in_array($code, [95, 96, 99], true)) {
            return 'Thunderstorm';
        }

        return 'Conditions unavailable';
    }

    private function weatherIcon(int $code): string
    {
        if ($code === 0) {
            return 'sun';
        }
        if (in_array($code, [1, 2], true)) {
            return 'partly-cloudy';
        }
        if (in_array($code, [3, 45, 48], true)) {
            return 'cloud';
        }
        if (in_array($code, [95, 96, 99], true)) {
            return 'storm';
        }
        if (in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true)) {
            return 'rain';
        }

        return 'cloud';
    }
}
