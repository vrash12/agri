<?php

namespace Tests\Unit;

use App\Models\Municipality;
use App\Modules\Climate\Infrastructure\ChirpsRainfallService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChirpsRainfallServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'climate.sources.chirps.enabled' => true, 'climate.sources.chirps.baseline_years' => 3,
            'climate.sources.chirps.poll_seconds' => 1, 'climate.sources.chirps.timeout_seconds' => 2]);
        Cache::flush();
        CarbonImmutable::setTestNow('2026-10-15 08:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_compares_recent_chirps_rainfall_with_a_rolling_reference(): void
    {
        $jobs = collect(range(1, 4))->map(fn (int $number) => sprintf('00000000-0000-4000-8000-%012d', $number))->all();
        $submits = Http::sequence();
        foreach ($jobs as $job) {
            $submits->push([$job]);
        }
        $data = Http::sequence()
            ->push(['data' => [['value' => ['avg' => 20.0]]]])
            ->push(['data' => [['value' => ['avg' => 10.0]]]])
            ->push(['data' => [['value' => ['avg' => 10.0]]]])
            ->push(['data' => [['value' => ['avg' => 10.0]]]]);
        Http::fake([
            '*submitDataRequest*' => $submits,
            '*getDataRequestProgress*' => Http::response([100]),
            '*getDataFromRequest*' => $data,
        ]);
        $municipality = new Municipality(['name' => 'Ramos', 'province' => 'Tarlac']);
        $municipality->id = 12;
        $municipality->exists = true;

        $result = app(ChirpsRainfallService::class)->forMunicipality($municipality, ['latitude' => 15.6651, 'longitude' => 120.6412]);

        $this->assertTrue($result['available']);
        $this->assertSame(20.0, $result['rainfall_mm']);
        $this->assertSame(10.0, $result['baseline_mm']);
        $this->assertSame(100.0, $result['anomaly_percent']);
        $this->assertSame(3, $result['baseline_years']);
        Http::assertSentCount(12); // four jobs, each submit/progress/result
    }

    public function test_it_returns_a_safe_unavailable_state_for_invalid_coordinates(): void
    {
        Http::fake();
        $municipality = new Municipality(['name' => 'Ramos', 'province' => 'Tarlac']);
        $municipality->id = 12;
        $municipality->exists = true;

        $result = app(ChirpsRainfallService::class)->forMunicipality($municipality, ['latitude' => 999, 'longitude' => 120]);

        $this->assertFalse($result['available']);
        Http::assertNothingSent();
    }
}
