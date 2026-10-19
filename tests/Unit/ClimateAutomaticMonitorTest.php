<?php

namespace Tests\Unit;

use App\Modules\Climate\Application\ClimateAutomaticMonitor;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClimateAutomaticMonitorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $now = CarbonImmutable::parse('2026-10-19T10:00:00+00:00');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        config(['app.display_timezone' => 'Asia/Manila', 'climate.monitoring.high_temperature_c' => 35,
            'climate.monitoring.low_forecast_rain_mm' => 10, 'climate.monitoring.rainfall_deficit_percent' => 30]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_missing_readings_wait_without_inventing_zero_rainfall_or_official_levels(): void
    {
        $result = app(ClimateAutomaticMonitor::class)->summarize([]);

        $this->assertSame('waiting', $result['state']);
        $this->assertSame('0 of 2 rainfall sources ready', $result['coverage']);
        $this->assertSame([], $result['signals']);
        $this->assertNull($result['sources']['open_meteo']['reading']);
        $this->assertNull($result['sources']['chirps']['reading']);
        $this->assertStringContainsString('Missing data is not zero rainfall', $result['summary']);
        $this->assertStringContainsString('not an official El Niño or drought declaration', $result['limits']);
        $this->assertArrayNotHasKey('risk_level', $result);
        $this->assertArrayNotHasKey('affected_parcels', $result);
        Http::assertNothingSent();
    }

    public function test_current_complete_zero_rainfall_is_real_evidence_and_generates_inspection_prompts(): void
    {
        $evidence = ['open_meteo' => $this->weather(['value' => 0, 'metadata' => ['temperature_max_c' => 35]]),
            'chirps' => $this->chirps(['value' => 0, 'anomaly_percent' => 999])];
        $result = app(ClimateAutomaticMonitor::class)->summarize($evidence);

        $this->assertSame('attention', $result['state']);
        $this->assertSame('Check field conditions', $result['label']);
        $this->assertSame('2 of 2 rainfall sources ready', $result['coverage']);
        $this->assertTrue($result['sources']['open_meteo']['usable']);
        $this->assertTrue($result['sources']['chirps']['usable']);
        $this->assertSame(0, $result['sources']['open_meteo']['reading']['value']);
        $this->assertSame(['Hot weather ahead', 'Little rain forecast', 'Recent rain below reference'], array_column($result['signals'], 'title'));
        $this->assertStringContainsString('0.0 mm', $result['signals'][1]['detail']);
        $this->assertStringContainsString('100% less rain', $result['signals'][2]['detail']);
        $this->assertSame('2026-10-25', $result['signals'][0]['date']);
        $this->assertSame('2026-10-15', $result['signals'][2]['date']);
        $this->assertArrayNotHasKey('risk_level', $result);
        Http::assertNothingSent();
    }

    #[DataProvider('unusableWeather')]
    public function test_missing_stale_incomplete_or_changed_forecasts_cannot_generate_prompts(array $changes): void
    {
        $row = $this->weather(array_replace_recursive(['value' => 0, 'metadata' => ['temperature_max_c' => 40]], $changes));
        $result = app(ClimateAutomaticMonitor::class)->summarize(['open_meteo' => $row]);

        $this->assertFalse($result['sources']['open_meteo']['usable']);
        $this->assertSame('waiting', $result['state']);
        $this->assertSame([], $result['signals']);
    }

    public static function unusableWeather(): iterable
    {
        yield 'partial status' => [['status' => 'partial']];
        yield 'unavailable status' => [['status' => 'unavailable']];
        yield 'missing rain' => [['value' => null]];
        yield 'not numeric' => [['value' => 'unknown']];
        yield 'negative rain' => [['value' => -1]];
        yield 'not finite rain' => [['value' => INF]];
        yield 'NaN rain' => [['value' => NAN]];
        yield 'changed area' => [['context_current' => false]];
        yield 'missing context' => [['context_current' => null]];
        yield 'partial coverage' => [['metadata' => ['coverage_complete' => false]]];
        yield 'only six rain days' => [['metadata' => ['rain_days' => 6]]];
        yield 'only six forecast days' => [['metadata' => ['forecast_days' => 6]]];
        yield 'provider stale flag' => [['metadata' => ['is_stale' => true]]];
        yield 'missing fetched date' => [['fetched_at' => null]];
        yield 'unparseable fetched date' => [['fetched_at' => 'not-a-date']];
        yield 'future fetched timestamp' => [['fetched_at' => '2026-10-19T10:06:00+00:00']];
        yield 'over twelve hours old' => [['fetched_at' => '2026-10-18T21:59:59+00:00']];
        yield 'missing start date' => [['period_start' => null]];
        yield 'short period' => [['period_end' => '2026-10-24']];
        yield 'forecast starts tomorrow' => [['period_start' => '2026-10-20', 'period_end' => '2026-10-26']];
        yield 'forecast ended yesterday' => [['period_start' => '2026-10-12', 'period_end' => '2026-10-18']];
    }

    #[DataProvider('unusableChirps')]
    public function test_incomplete_or_outdated_historical_rainfall_cannot_trigger_an_inspection(array $changes): void
    {
        $row = $this->chirps(array_replace_recursive(['value' => 0], $changes));
        $result = app(ClimateAutomaticMonitor::class)->summarize(['chirps' => $row]);

        $this->assertFalse($result['sources']['chirps']['usable']);
        $this->assertSame('waiting', $result['state']);
        $this->assertSame([], $result['signals']);
    }

    public static function unusableChirps(): iterable
    {
        yield 'missing rainfall' => [['value' => null]];
        yield 'negative rainfall' => [['value' => -1]];
        yield 'context changed' => [['context_current' => false]];
        yield 'provider stale flag' => [['metadata' => ['is_stale' => true]]];
        yield 'not complete' => [['metadata' => ['coverage_complete' => false]]];
        yield 'missing daily measurements' => [['metadata' => ['days_available' => 29]]];
        yield 'only 29 day interval' => [['period_start' => '2026-09-17']];
        yield 'current day is incomplete' => [['period_start' => '2026-09-20', 'period_end' => '2026-10-19']];
        yield 'ends over 21 days ago' => [['period_start' => '2026-08-29', 'period_end' => '2026-09-27']];
        yield 'fetched over 48 hours ago' => [['fetched_at' => '2026-10-17T09:59:59+00:00']];
        yield 'bad period date' => [['period_end' => 'not-a-date']];
    }

    #[DataProvider('incompleteBaselines')]
    public function test_missing_partial_or_zero_baselines_never_become_a_rainfall_deficit(array $changes): void
    {
        $row = $this->chirps(array_replace_recursive(['value' => 0, 'anomaly_percent' => -100], $changes));
        $result = app(ClimateAutomaticMonitor::class)->summarize(['chirps' => $row]);

        $this->assertTrue($result['sources']['chirps']['usable'], 'The dated rainfall total is still valid even without a comparison');
        $this->assertSame('monitoring', $result['state']);
        $this->assertSame([], $result['signals']);
    }

    public static function incompleteBaselines(): iterable
    {
        yield 'missing baseline' => [['baseline_value' => null]];
        yield 'zero baseline' => [['baseline_value' => 0]];
        yield 'negative baseline' => [['baseline_value' => -1]];
        yield 'infinite baseline' => [['baseline_value' => INF]];
        yield 'partial reference years' => [['metadata' => ['baseline_years' => 2]]];
        yield 'only two requested years' => [['metadata' => ['baseline_years' => 2, 'baseline_years_requested' => 2]]];
        yield 'unknown requested years' => [['metadata' => ['baseline_years_requested' => 0]]];
    }

    public function test_complete_reference_totals_override_a_conflicting_stored_anomaly(): void
    {
        $result = app(ClimateAutomaticMonitor::class)->summarize(['chirps' => $this->chirps(['value' => 70, 'anomaly_percent' => 999])]);
        $this->assertSame('attention', $result['state']);
        $this->assertStringContainsString('30% less rain', $result['signals'][0]['detail']);
        $this->assertStringContainsString('3-year reference', $result['signals'][0]['detail']);

        $result = app(ClimateAutomaticMonitor::class)->summarize(['chirps' => $this->chirps(['value' => 71, 'anomaly_percent' => -100])]);
        $this->assertSame('monitoring', $result['state']);
        $this->assertSame([], $result['signals']);
    }

    public function test_temperature_requires_complete_temperature_coverage_and_rain_uses_strict_threshold(): void
    {
        $result = app(ClimateAutomaticMonitor::class)->summarize(['open_meteo' => $this->weather([
            'value' => 10, 'metadata' => ['temperature_max_c' => 45, 'temperature_days' => 6],
        ])]);
        $this->assertSame('monitoring', $result['state']);
        $this->assertSame([], $result['signals']);

        $result = app(ClimateAutomaticMonitor::class)->summarize(['open_meteo' => $this->weather([
            'value' => 9.9, 'metadata' => ['temperature_max_c' => 34.9],
        ])]);
        $this->assertSame(['Little rain forecast'], array_column($result['signals'], 'title'));
    }

    public function test_freshness_cutoffs_and_local_day_are_consistent(): void
    {
        $result = app(ClimateAutomaticMonitor::class)->summarize([
            'open_meteo' => $this->weather(['fetched_at' => '2026-10-18T22:00:00+00:00']),
            'chirps' => $this->chirps(['fetched_at' => '2026-10-17T10:00:00+00:00', 'period_start' => '2026-08-30', 'period_end' => '2026-09-28']),
        ]);
        $this->assertTrue($result['sources']['open_meteo']['usable']);
        $this->assertTrue($result['sources']['chirps']['usable']);
        $this->assertSame('monitoring', $result['state']);

        // After Manila midnight, the next day's full forecast is current even
        // while the UTC calendar still shows the previous day.
        $now = CarbonImmutable::parse('2026-10-19T17:00:00+00:00');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        $result = app(ClimateAutomaticMonitor::class)->summarize(['open_meteo' => $this->weather([
            'fetched_at' => $now, 'period_start' => '2026-10-20', 'period_end' => '2026-10-26',
        ])]);
        $this->assertTrue($result['sources']['open_meteo']['usable']);
    }

    private function weather(array $changes = []): array
    {
        return array_replace_recursive(['status' => 'available', 'context_current' => true, 'value' => 25,
            'period_start' => '2026-10-19', 'period_end' => '2026-10-25', 'fetched_at' => '2026-10-19T10:00:00+00:00',
            'metadata' => ['coverage_complete' => true, 'forecast_days' => 7, 'rain_days' => 7,
                'temperature_days' => 7, 'temperature_max_c' => 30, 'is_stale' => false]], $changes);
    }

    private function chirps(array $changes = []): array
    {
        return array_replace_recursive(['status' => 'available', 'context_current' => true, 'value' => 100,
            'baseline_value' => 100, 'anomaly_percent' => 0, 'period_start' => '2026-09-16', 'period_end' => '2026-10-15',
            'fetched_at' => '2026-10-19T10:00:00+00:00',
            'metadata' => ['coverage_complete' => true, 'days_available' => 30, 'baseline_years' => 3,
                'baseline_years_requested' => 3, 'is_stale' => false]], $changes);
    }
}
