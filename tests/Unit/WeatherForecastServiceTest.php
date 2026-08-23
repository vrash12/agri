<?php

namespace Tests\Unit;

use App\Models\Municipality;
use App\Services\WeatherForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherForecastServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('weather.cache_minutes', 30);
        config()->set('weather.stale_hours', 12);
        config()->set('weather.timeout_seconds', 2);
        Cache::flush();
        CarbonImmutable::setTestNow('2026-08-24 09:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_normalizes_forecast_data_and_builds_farm_advisories(): void
    {
        Http::fake([
            '*' => Http::response($this->forecastPayload(), 200),
        ]);

        $forecast = app(WeatherForecastService::class)
            ->forMunicipality($this->municipality('Anao', 11));

        $this->assertTrue($forecast['available']);
        $this->assertFalse($forecast['is_stale']);
        $this->assertSame('Anao', $forecast['municipality']['name']);
        $this->assertSame('Thunderstorm', $forecast['current']['condition']);
        $this->assertSame(7, count($forecast['daily']));
        $this->assertGreaterThanOrEqual(50, $forecast['summary']['maximum_daily_rain']);
        $this->assertTrue(collect($forecast['advisories'])->contains(
            fn (array $advisory) => $advisory['category'] === 'Rainfall risk'
                && $advisory['severity'] === 'high'
        ));
        $this->assertTrue(collect($forecast['advisories'])->contains(
            fn (array $advisory) => $advisory['category'] === 'Wind risk'
        ));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/forecast')
                && $request['forecast_days'] === 7
                && $request['timezone'] === 'Asia/Manila';
        });
    }

    public function test_it_reuses_cached_forecasts_for_the_same_municipality(): void
    {
        Http::fake([
            '*' => Http::response($this->forecastPayload(), 200),
        ]);

        $service = app(WeatherForecastService::class);
        $municipality = $this->municipality('Anao', 12);

        $service->forMunicipality($municipality);
        $service->forMunicipality($municipality);

        Http::assertSentCount(1);
    }

    public function test_it_returns_a_safe_unavailable_state_when_the_provider_fails(): void
    {
        Http::fake([
            '*' => Http::response(['reason' => 'provider unavailable'], 503),
        ]);

        $forecast = app(WeatherForecastService::class)
            ->forMunicipality($this->municipality('Anao', 13));

        $this->assertFalse($forecast['available']);
        $this->assertSame([], $forecast['daily']);
        $this->assertStringContainsString(
            'temporarily unavailable',
            $forecast['status_message']
        );
    }

    public function test_it_does_not_call_the_provider_for_an_unconfigured_municipality(): void
    {
        Http::fake();

        $forecast = app(WeatherForecastService::class)
            ->forMunicipality($this->municipality('Unknown Office', 14));

        $this->assertFalse($forecast['available']);
        $this->assertStringContainsString(
            'reference point',
            $forecast['status_message']
        );
        Http::assertNothingSent();
    }

    private function municipality(string $name, int $id): Municipality
    {
        $municipality = new Municipality([
            'name' => $name,
            'province' => 'Tarlac',
            'is_active' => true,
        ]);
        $municipality->id = $id;
        $municipality->exists = true;

        return $municipality;
    }

    /** @return array<string, mixed> */
    private function forecastPayload(): array
    {
        $dates = collect(range(0, 6))
            ->map(fn (int $day) => CarbonImmutable::now()->addDays($day)->toDateString())
            ->all();
        $hours = collect(range(0, 47))
            ->map(fn (int $hour) => CarbonImmutable::now()->startOfHour()->addHours($hour)->format('Y-m-d\TH:i'))
            ->all();

        return [
            'timezone' => 'Asia/Manila',
            'current' => [
                'time' => '2026-08-24T09:00',
                'temperature_2m' => 34.2,
                'relative_humidity_2m' => 74,
                'apparent_temperature' => 39.1,
                'precipitation' => 1.2,
                'weather_code' => 95,
                'wind_speed_10m' => 18.5,
                'wind_gusts_10m' => 32.0,
            ],
            'daily' => [
                'time' => $dates,
                'weather_code' => [95, 81, 61, 2, 2, 1, 3],
                'temperature_2m_max' => [35.2, 33.1, 32.8, 34.0, 34.4, 35.1, 33.7],
                'temperature_2m_min' => [25.0, 24.8, 24.4, 25.1, 25.3, 25.0, 24.9],
                'precipitation_sum' => [58.4, 24.0, 12.0, 2.0, 1.0, 0.5, 3.0],
                'precipitation_probability_max' => [95, 88, 72, 35, 25, 20, 45],
                'wind_speed_10m_max' => [32.0, 25.0, 21.0, 18.0, 16.0, 15.0, 20.0],
                'wind_gusts_10m_max' => [67.0, 48.0, 38.0, 31.0, 29.0, 26.0, 35.0],
                'et0_fao_evapotranspiration' => [3.8, 3.4, 3.2, 4.5, 4.8, 5.0, 4.1],
                'sunrise' => array_fill(0, 7, '2026-08-24T05:45'),
                'sunset' => array_fill(0, 7, '2026-08-24T18:10'),
            ],
            'hourly' => [
                'time' => $hours,
                'temperature_2m' => array_fill(0, 48, 31.5),
                'relative_humidity_2m' => array_fill(0, 48, 78),
                'precipitation_probability' => array_fill(0, 48, 80),
                'precipitation' => array_fill(0, 48, 1.4),
                'wind_gusts_10m' => array_fill(0, 48, 42.0),
                'soil_moisture_0_to_1cm' => array_fill(0, 48, 0.36),
            ],
        ];
    }
}
