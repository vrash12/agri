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
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_complete_dated_periods_have_verified_dataset_labels_and_cached_reuse(): void
    {
        $this->fakePeriods();
        $result = $this->read();
        $this->assertTrue($result['available']);
        $this->assertTrue($result['coverage_complete']);
        $this->assertSame(600.0, $result['rainfall_mm']);
        $this->assertSame(300.0, $result['baseline_mm']);
        $this->assertSame(100.0, $result['anomaly_percent']);
        $this->assertSame(3, $result['baseline_years']);
        $this->assertSame(30, $result['days_available']);
        $this->assertSame('2026-09-11', $result['period_start']);
        $this->assertSame('2026-10-10', $result['period_end']);
        $this->assertSame('CHIRPS via ClimateSERV', $result['provider']);
        $this->assertSame('climateserv-chirps-0', $result['dataset']);
        Http::assertSentCount(12);
        $this->read();
        Http::assertSentCount(12);
    }

    public function test_missing_days_never_become_thirty_day_totals_or_zero_rain(): void
    {
        $this->fakePeriods(fn ($rows) => array_slice($rows, 1));
        $result = $this->read();
        $this->assertFalse($result['available']);
        $this->assertFalse($result['coverage_complete']);
        $this->assertNull($result['rainfall_mm']);
        $this->assertNull($result['anomaly_percent']);
        Http::assertSentCount(3);
    }

    public function test_duplicate_dates_cannot_fill_missing_days(): void
    {
        $this->fakePeriods(function ($rows) {
            $rows[29] = $rows[0];

            return $rows;
        });
        $this->assertFalse($this->read()['available']);
    }

    public function test_undated_or_ambiguous_display_dates_are_not_evidence(): void
    {
        $this->fakePeriods(fn ($rows) => array_map(fn ($row) => ['date' => '1/2/2026', 'value' => $row['value']], $rows));
        $this->assertFalse($this->read()['available']);
    }

    public function test_partial_baseline_never_generates_rainfall_anomaly(): void
    {
        $this->fakePeriods(null, fn ($rows, $year) => $year === 2024 ? array_slice($rows, 1) : $rows);
        $result = $this->read();
        $this->assertTrue($result['available']);
        $this->assertSame(600.0, $result['rainfall_mm']);
        $this->assertSame(2, $result['baseline_years']);
        $this->assertSame(3, $result['baseline_years_requested']);
        $this->assertNull($result['baseline_mm']);
        $this->assertNull($result['anomaly_percent']);
    }

    public function test_nodata_does_not_create_zero_rainfall(): void
    {
        $this->fakePeriods(function ($rows) {
            $rows[15]['value']['avg'] = -9999;

            return $rows;
        });
        $this->assertFalse($this->read()['available']);
    }

    public function test_true_zero_rain_is_retained_but_zero_reference_has_no_percentage(): void
    {
        $zero = fn ($rows) => array_map(fn ($row) => array_replace($row, ['value' => ['avg' => 0]]), $rows);
        $this->fakePeriods($zero, $zero);
        $result = $this->read();
        $this->assertSame(0.0, $result['rainfall_mm']);
        $this->assertSame(0.0, $result['baseline_mm']);
        $this->assertNull($result['anomaly_percent']);
    }

    public function test_actual_complete_dates_define_period_not_requested_end(): void
    {
        $this->fakePeriods(fn () => $this->rows(CarbonImmutable::parse('2026-10-05'), 20));
        $result = $this->read();
        $this->assertTrue($result['available']);
        $this->assertSame('2026-10-05', $result['observed_on']);
        $this->assertSame('2026-09-06', $result['period_start']);
    }

    public function test_historical_periods_have_thirty_days_across_leap_years(): void
    {
        CarbonImmutable::setTestNow('2025-03-03 08:00:00');
        $requests = [];
        $this->fakePeriods(null, null, $requests);
        $this->assertTrue($this->read()['available']);
        foreach (array_slice($requests, 1) as [$start, $end]) {
            $this->assertSame(29.0, CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)));
        }
    }

    public function test_pending_jobs_resume_without_duplicate_processing_submissions(): void
    {
        $submissions = 0;
        Http::fake(function ($request) use (&$submissions) {
            if (str_contains($request->url(), 'submitDataRequest')) {
                $submissions++;

                return Http::response(['00000000-0000-4000-8000-000000000001']);
            }

            return Http::response([1]);
        });
        $this->assertFalse($this->read()['available']);
        $this->assertFalse($this->read(true)['available']);
        $this->assertSame(1, $submissions);
    }

    public function test_invalid_coordinates_cannot_call_provider(): void
    {
        Http::fake();
        $result = app(ChirpsRainfallService::class)->forMunicipality($this->municipality(), ['latitude' => 999, 'longitude' => 120]);
        $this->assertFalse($result['available']);
        Http::assertNothingSent();
    }

    private function read(bool $force = false): array
    {
        return app(ChirpsRainfallService::class)->forMunicipality($this->municipality(), ['latitude' => 15.6651, 'longitude' => 120.6412], $force);
    }

    private function municipality(): Municipality
    {
        $municipality = new Municipality(['name' => 'Synthetic area', 'province' => 'Synthetic province']);
        $municipality->id = 12;
        $municipality->exists = true;

        return $municipality;
    }

    private function rows(CarbonImmutable $end, float $value): array
    {
        $rows = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = $end->subDays($i);
            $rows[] = ['epochTime' => (string) $date->timestamp, 'value' => ['avg' => $value]];
        }

        return $rows;
    }

    private function fakePeriods(?callable $current = null, ?callable $baseline = null, ?array &$requests = null): void
    {
        $jobs = [];
        Http::fake(function ($request) use (&$jobs, $current, $baseline, &$requests) {
            if (str_contains($request->url(), 'submitDataRequest')) {
                $end = CarbonImmutable::createFromFormat('!m/d/Y', $request['endtime'], 'UTC');
                $start = CarbonImmutable::createFromFormat('!m/d/Y', $request['begintime'], 'UTC');
                $requests[] = [$start->toDateString(), $end->toDateString()];
                $id = sprintf('00000000-0000-4000-8000-%012d', count($jobs) + 1);
                $isCurrent = $end->year === CarbonImmutable::now('UTC')->year;
                $rows = $this->rows($end, $isCurrent ? 20 : 10);
                $jobs[$id] = $isCurrent && $current ? $current($rows) : (! $isCurrent && $baseline ? $baseline($rows, $end->year) : $rows);

                return Http::response([$id]);
            }
            if (str_contains($request->url(), 'getDataRequestProgress')) {
                return Http::response([100]);
            }
            if (str_contains($request->url(), 'getDataFromRequest')) {
                return Http::response(['data' => $jobs[$request['id']]]);
            }

            return Http::response([], 500);
        });
    }
}
