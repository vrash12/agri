<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Modules\Climate\Application\ParcelSatelliteHistory;
use App\Modules\Climate\Application\SatelliteIndex;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParcelSatelliteHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'sentinel.enabled' => true,
            'sentinel.client_id' => 'synthetic', 'sentinel.client_secret' => 'synthetic']);
        Cache::flush();
        Http::preventStrayRequests();
        CarbonImmutable::setTestNow('2026-10-19 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_four_indices_keep_dated_comparable_summaries_without_provider_reads(): void
    {
        $service = app(ParcelSatelliteHistory::class);
        $plot = $this->plot();
        foreach (SatelliteIndex::IDS as $index) {
            $service->remember($plot, $this->reading($index, '2026-10-10', 0.6));
            $service->remember($plot, $this->reading($index, '2026-10-17', 0.4));
            $service->remember($plot, $this->reading($index, '2026-10-17', 0.42));
            $rows = $service->forPlot($plot)[$index];
            $this->assertCount(2, $rows);
            $this->assertSame([0.6, 0.42], array_column($rows, 'mean'));
            $this->assertSame($rows[0]['series_key'], $rows[1]['series_key']);
            $this->assertFalse($rows[1]['is_stale']);
            $this->assertArrayNotHasKey('geometry', $rows[0]);
        }
        Http::assertNothingSent();
    }

    public function test_changed_settings_do_not_mix_comparison_series(): void
    {
        $service = app(ParcelSatelliteHistory::class);
        $plot = $this->plot();
        $service->remember($plot, $this->reading('ndvi', '2026-10-10', 0.6));
        $original = $service->forPlot($plot)['ndvi'][0]['series_key'];
        $service->remember($plot, array_replace($this->reading('ndvi', '2026-10-17', 0.4), ['scene_cloud_limit' => 100]));
        $rows = $service->forPlot($plot)['ndvi'];
        $this->assertCount(1, $rows);
        $this->assertNotSame($original, $rows[0]['series_key']);
    }

    public function test_boundary_owner_office_and_credential_changes_hide_old_evidence(): void
    {
        $service = app(ParcelSatelliteHistory::class);
        foreach (['boundary', 'owner', 'office', 'credential'] as $change) {
            $plot = $this->plot();
            $service->remember($plot, $this->reading('ndvi', '2026-10-10', 0.6));
            if ($change === 'boundary') {
                $plot->polygon_json = [[120.1, 15.1], [120.2, 15.1], [120.2, 15.2]];
            } elseif ($change === 'owner') {
                $plot->farmer_id = 2;
            } elseif ($change === 'office') {
                $plot->farmer->municipality_id = 2;
            } else {
                config(['sentinel.client_secret' => 'rotated-synthetic']);
            }
            $this->assertSame([], $service->forPlot($plot)['ndvi'], $change);
        }
    }

    public function test_bad_empty_future_and_expired_rows_cannot_become_evidence(): void
    {
        $service = app(ParcelSatelliteHistory::class);
        $plot = $this->plot();
        foreach ([['2026-10-20', 0.6], ['2026-02-30', 0.6], ['2026-01-01', 0.6], ['2026-10-10', 1.2]] as [$date, $mean]) {
            $service->remember($plot, $this->reading('ndvi', $date, $mean));
        }
        $empty = $this->reading('ndvi', '2026-10-10', 0.5);
        $empty['observations'][0]['valid_pixels'] = 0;
        $service->remember($plot, $empty);
        $this->assertSame([], $service->forPlot($plot)['ndvi']);
        $service->remember($plot, $this->reading('ndvi', '2026-09-01', 0.5));
        $this->assertTrue($service->forPlot($plot)['ndvi'][0]['is_stale']);
        CarbonImmutable::setTestNow('2027-01-01 12:00:00 UTC');
        $this->assertSame([], $service->forPlot($plot)['ndvi']);
    }

    public function test_read_revalidates_cached_measurements_dates_grid_and_series_instead_of_trusting_them(): void
    {
        $service = app(ParcelSatelliteHistory::class);
        $plot = $this->plot();
        $service->remember($plot, $this->reading('ndvi', '2026-10-10', .6));
        $key = $this->cacheKey($service, $plot);
        $valid = Cache::get($key)['ndvi'][0];
        foreach ([
            ['mean' => NAN], ['mean' => INF], ['mean' => true], ['mean' => 1.1], ['mean' => null],
            ['date' => '2026-02-30'], ['date' => '2026-10-20'], ['date' => 'tomorrow'], ['date' => '2026-01-01'],
            ['valid_pixels' => 0], ['valid_pixels' => 1.5], ['valid_pixels' => 300001],
            ['grid_metres' => 20], ['grid_metres' => '10'], ['scene_cloud_limit' => 61],
            ['scene_cloud_limit' => null], ['series_key' => str_repeat('a', 64)],
            ['checked_at' => '2026-10-09T12:00:00+00:00'], ['checked_at' => '2026-10-20T00:00:00+00:00'],
            ['checked_at' => '2026-02-30T12:00:00+00:00'], ['checked_at' => null],
        ] as $change) {
            Cache::put($key, ['ndvi' => [array_replace($valid, $change)]]);
            $this->assertSame([], $service->forPlot($plot)['ndvi'], implode(', ', array_keys($change)));
        }
        Http::assertNothingSent();
    }

    public function test_reads_are_bounded_deduplicated_and_strip_unknown_cached_fields(): void
    {
        $service = app(ParcelSatelliteHistory::class);
        $plot = $this->plot();
        $service->remember($plot, $this->reading('ndvi', '2026-10-10', .6));
        $key = $this->cacheKey($service, $plot);
        $valid = Cache::get($key)['ndvi'][0];
        $rows = [];
        for ($age = 90; $age >= 0; $age--) {
            $rows[] = array_replace($valid, ['date' => CarbonImmutable::now('UTC')->subDays($age)->toDateString(),
                'source' => 'untrusted cached source', 'geometry' => 'must not leave cache', 'is_stale' => false]);
        }
        Cache::put($key, ['ndvi' => $rows, 'ndmi' => 'malformed', 'unknown' => ['must not leave cache']]);
        $result = $service->forPlot($plot);
        $this->assertSame(SatelliteIndex::IDS, array_keys($result));
        $this->assertCount(90, $result['ndvi']);
        $this->assertSame(CarbonImmutable::now('UTC')->subDays(89)->toDateString(), $result['ndvi'][0]['date']);
        $this->assertSame([], $result['ndmi']);
        $this->assertTrue($result['ndvi'][0]['is_stale']);
        $this->assertFalse($result['ndvi'][89]['is_stale']);
        $this->assertSame('Copernicus Sentinel-2 Level-2A', $result['ndvi'][0]['source']);
        $this->assertArrayNotHasKey('geometry', $result['ndvi'][0]);
        Cache::put($key, ['ndvi' => [$valid, $valid]]);
        $this->assertCount(1, $service->forPlot($plot)['ndvi']);
    }

    public function test_remember_discards_corrupt_prior_rows_and_preserves_other_valid_indices(): void
    {
        $service = app(ParcelSatelliteHistory::class);
        $plot = $this->plot();
        $service->remember($plot, $this->reading('ndvi', '2026-10-10', .6));
        $service->remember($plot, $this->reading('evi', '2026-10-11', 1.4));
        $key = $this->cacheKey($service, $plot);
        $history = Cache::get($key);
        $history['ndvi'][0]['mean'] = INF;
        $history['savi'] = 'invalid';
        Cache::put($key, $history);
        $service->remember($plot, $this->reading('ndvi', '2026-10-18', .4));
        $result = $service->forPlot($plot);
        $this->assertSame([.4], array_column($result['ndvi'], 'mean'));
        $this->assertSame([1.4], array_column($result['evi'], 'mean'));
        $this->assertSame([], $result['savi']);
    }

    public function test_valid_raw_savi_evi_values_are_retained_and_wrong_incoming_grids_are_rejected(): void
    {
        $service = app(ParcelSatelliteHistory::class);
        $plot = $this->plot();
        $service->remember($plot, $this->reading('savi', '2026-10-10', 1.2));
        $service->remember($plot, $this->reading('evi', '2026-10-10', -2.5));
        $service->remember($plot, array_replace($this->reading('ndmi', '2026-10-10', .4), ['resolution_m' => 10]));
        $result = $service->forPlot($plot);
        $this->assertSame(1.2, $result['savi'][0]['mean']);
        $this->assertSame(-2.5, $result['evi'][0]['mean']);
        $this->assertSame([], $result['ndmi']);
    }

    private function cacheKey(ParcelSatelliteHistory $service, FarmPlot $plot): string
    {
        return (new \ReflectionMethod($service, 'key'))->invoke($service, $plot);
    }

    private function plot(): FarmPlot
    {
        $plot = new FarmPlot(['farmer_id' => 1, 'polygon_json' => [[120, 15], [120.01, 15], [120.01, 15.01]]]);
        $plot->id = 1;
        $plot->setRelation('farmer', new Farmer(['municipality_id' => 1]));

        return $plot;
    }

    private function reading(string $index, string $date, float $mean): array
    {
        return ['index' => $index, 'resolution_m' => SatelliteIndex::definition($index)['resolution_m'],
            'scene_cloud_limit' => 60, 'observations' => [['date' => $date, 'mean' => $mean, 'valid_pixels' => 100, 'status' => 'available']]];
    }
}
