<?php

namespace Tests\Feature;

use App\Models\ClimateRiskSourceReading;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Modules\Climate\Application\ClimateRefreshStatus;
use App\Modules\Climate\Application\ClimateRiskSourceRecorder;
use App\Modules\Climate\Infrastructure\WeatherForecastService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RefreshClimateSourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'climate.sources.open_meteo.enabled' => true, 'climate.sources.chirps.enabled' => true,
            'climate.sources.sentinel2.enabled' => false, 'climate.refresh_batch_size' => 100]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 0));
        $this->schema();
    }

    public function test_scheduled_refresh_only_visits_used_municipalities_and_records_complete_dated_evidence(): void
    {
        $this->weather([1, 2, 3]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo']])->assertSuccessful();
        $this->assertSame([1, 2, 3], ClimateRiskSourceReading::orderBy('municipality_id')->pluck('municipality_id')->all());
        $reading = ClimateRiskSourceReading::where('municipality_id', 1)->firstOrFail();
        $this->assertSame(14.0, $reading->value);
        $this->assertTrue($reading->metadata['coverage_complete']);
        $this->assertSame(7, $reading->metadata['rain_days']);
        $this->assertSame(7, $reading->metadata['temperature_days']);
        $this->assertSame('available', $reading->status);
        $this->assertNotEmpty($reading->metadata['context_fingerprint']);
        $this->assertSame('finished', app(ClimateRefreshStatus::class)->read('open-meteo')['state']);
        $this->assertSame(1, DB::table('climate_risk_assessments')->count());
        Http::assertNothingSent();
    }

    public function test_fresh_matching_sources_skip_provider_work(): void
    {
        $this->weather([1, 2, 3]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo']])->assertSuccessful();
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo']])->assertSuccessful();
    }

    public function test_boundary_reference_change_refreshes_even_inside_normal_window(): void
    {
        $this->weather([1, 1]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo'], '--municipality' => ['1']])->assertSuccessful();
        $previous = ClimateRiskSourceReading::firstOrFail()->metadata['context_fingerprint'];
        DB::table('municipality_boundaries')->where('municipality_id', 1)->update(['centroid_lat' => 16]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo'], '--municipality' => ['1']])->assertSuccessful();
        $this->assertNotSame($previous, ClimateRiskSourceReading::firstOrFail()->metadata['context_fingerprint']);
    }

    public function test_explicit_unused_active_area_is_allowed_without_scanning_every_area(): void
    {
        $this->weather([4]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo'], '--municipality' => ['4']])->assertSuccessful();
        $this->assertSame([4], ClimateRiskSourceReading::pluck('municipality_id')->all());
    }

    public function test_batches_continue_to_next_used_area(): void
    {
        $this->weather([1, 2, 3]);
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo'], '--batch-size' => '1'])->assertSuccessful();
        }
        $this->assertSame(3, ClimateRiskSourceReading::count());
    }

    #[DataProvider('invalidOptions')]
    public function test_invalid_options_fail_closed_without_provider_work(array $options): void
    {
        $this->weather([]);
        $this->artisan('climate:refresh-sources', $options)->assertFailed();
        $this->assertSame(0, ClimateRiskSourceReading::count());
        Http::assertNothingSent();
    }

    public static function invalidOptions(): array
    {
        return [
            'unknown provider' => [['--provider' => ['invalid']]],
            'mixed provider' => [['--provider' => ['open-meteo', 'typo']]],
            'bad municipality' => [['--municipality' => ['abc']]],
            'mixed IDs' => [['--municipality' => ['1', 'bad']]],
            'missing municipality' => [['--municipality' => ['999']]],
            'inactive municipality' => [['--municipality' => ['8']]],
            'zero batch' => [['--batch-size' => '0']],
            'large batch' => [['--batch-size' => '201']],
        ];
    }

    public function test_partial_rainfall_is_not_persisted_as_a_complete_total(): void
    {
        $forecast = $this->forecast();
        $forecast['daily'][3]['precipitation_sum'] = null;
        $this->weather([1], $forecast);
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo'], '--municipality' => ['1']])->assertSuccessful();
        $reading = ClimateRiskSourceReading::firstOrFail();
        $this->assertNull($reading->value);
        $this->assertFalse($reading->metadata['coverage_complete']);
        $this->assertSame(6, $reading->metadata['rain_days']);
        $this->assertSame('partial', $reading->status);
    }

    public function test_duplicate_forecast_dates_are_not_complete_coverage(): void
    {
        $forecast = $this->forecast();
        $forecast['daily'][6]['date'] = $forecast['daily'][0]['date'];
        $this->weather([1], $forecast);
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo'], '--municipality' => ['1']])->assertSuccessful();
        $this->assertFalse(ClimateRiskSourceReading::firstOrFail()->metadata['coverage_complete']);
    }

    public function test_stale_fallback_retains_its_real_fetch_time(): void
    {
        $forecast = $this->forecast();
        $forecast['is_stale'] = true;
        $forecast['fetched_at'] = now()->subHours(9)->toIso8601String();
        $this->weather([1], $forecast);
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo'], '--municipality' => ['1']])->assertSuccessful();
        $reading = ClimateRiskSourceReading::firstOrFail();
        $this->assertTrue($reading->metadata['is_stale']);
        $this->assertTrue($reading->fetched_at->equalTo(now()->subHours(9)));
        $this->assertSame('partial', $reading->status);
    }

    public function test_concurrent_command_cannot_duplicate_a_refresh(): void
    {
        $this->weather([]);
        $lock = Cache::lock('climate.sources.command.v2', 900);
        $lock->get();
        try {
            $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo']])->assertSuccessful();
            $this->assertSame(0, ClimateRiskSourceReading::count());
        } finally {
            $lock->release();
        }
    }

    public function test_sentinel_stays_opt_in_without_provider_requests(): void
    {
        $this->weather([]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['sentinel2']])->assertSuccessful();
        $this->assertSame(0, ClimateRiskSourceReading::count());
        $this->assertNull(app(ClimateRefreshStatus::class)->read('sentinel2'));
        Http::assertNothingSent();
    }

    public function test_opted_in_satellite_limit_is_global_across_all_municipalities(): void
    {
        config(['climate.sources.sentinel2.enabled' => true, 'climate.sources.sentinel2.max_plots' => 2,
            'sentinel.enabled' => true, 'sentinel.client_id' => 'synthetic-client', 'sentinel.client_secret' => 'synthetic-secret']);
        DB::table('farmers')->insert(['id' => 2, 'municipality_id' => 2]);
        foreach ([1 => 1, 2 => 2, 3 => 2] as $plotId => $farmerId) {
            DB::table('farm_plots')->insert(['id' => $plotId, 'farmer_id' => $farmerId,
                'polygon_json' => json_encode([['lat' => 15.5, 'lng' => 120.5], ['lat' => 15.501, 'lng' => 120.5], ['lat' => 15.5, 'lng' => 120.501]])]);
        }
        Http::fake([
            'https://identity.dataspace.copernicus.eu/*' => Http::response(['access_token' => 'synthetic-token', 'expires_in' => 300]),
            'https://sh.dataspace.copernicus.eu/statistics/v1' => Http::response(['status' => 'OK', 'data' => []]),
        ]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['sentinel2']])->assertSuccessful();
        $statistics = Http::recorded(fn ($request) => str_contains($request->url(), 'statistics/v1'));
        $this->assertCount(2, $statistics);
        $this->assertSame(0, ClimateRiskSourceReading::count());
    }

    public function test_each_provider_heartbeat_keeps_its_own_failures(): void
    {
        config(['climate.sources.sentinel2.enabled' => true, 'sentinel.enabled' => true,
            'sentinel.client_id' => 'synthetic-client', 'sentinel.client_secret' => 'synthetic-secret']);
        DB::table('farm_plots')->insert(['id' => 1, 'farmer_id' => 1, 'polygon_json' => json_encode([['lat' => 15.5, 'lng' => 120.5]])]);
        $this->weather([1]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['open-meteo', 'sentinel2'], '--municipality' => ['1']])->assertSuccessful();
        $weather = app(ClimateRefreshStatus::class)->read('open-meteo');
        $satellite = app(ClimateRefreshStatus::class)->read('sentinel2');
        $this->assertSame('finished', $weather['state']);
        $this->assertSame(0, $weather['counts']['failed']);
        $this->assertSame(1, $weather['counts']['open_meteo']);
        $this->assertSame('needs_review', $satellite['state']);
        $this->assertSame(1, $satellite['counts']['failed']);
        $this->assertSame(0, $satellite['counts']['open_meteo']);
        Http::assertNothingSent();
    }

    #[DataProvider('changedPlotContexts')]
    public function test_recent_satellite_reading_only_skips_an_unchanged_plot(string $change): void
    {
        config(['climate.sources.sentinel2.enabled' => true, 'climate.sources.sentinel2.max_plots' => 1,
            'sentinel.enabled' => true, 'sentinel.client_id' => 'synthetic-client', 'sentinel.client_secret' => 'synthetic-secret']);
        $ring = [['lat' => 15.5, 'lng' => 120.5], ['lat' => 15.501, 'lng' => 120.5], ['lat' => 15.5, 'lng' => 120.501]];
        DB::table('farm_plots')->insert(['id' => 1, 'farmer_id' => 1, 'polygon_json' => json_encode($ring)]);
        $plot = FarmPlot::with('farmer.municipality')->findOrFail(1);
        app(ClimateRiskSourceRecorder::class)->plot($plot, 'sentinel2', 'ndvi', [
            'observed_on' => now()->subDay()->toDateString(), 'value' => .4, 'unit' => 'NDVI', 'status' => 'available',
            'source_label' => 'Synthetic NDVI', 'source_url' => 'https://example.test/satellite', 'metadata' => ['is_stale' => false],
        ]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['sentinel2'], '--municipality' => ['1']])->assertSuccessful();
        Http::assertNothingSent();

        if ($change === 'boundary') {
            $ring[1]['lat'] = 15.502;
            DB::table('farm_plots')->where('id', 1)->update(['polygon_json' => json_encode($ring)]);
        } else {
            DB::table('farmers')->insert(['id' => 2, 'municipality_id' => 1]);
            DB::table('farm_plots')->where('id', 1)->update(['farmer_id' => 2]);
        }
        Http::fake([
            'https://identity.dataspace.copernicus.eu/*' => Http::response(['access_token' => 'synthetic-token', 'expires_in' => 300]),
            'https://sh.dataspace.copernicus.eu/statistics/v1' => Http::response(['status' => 'OK', 'data' => []]),
        ]);
        $this->artisan('climate:refresh-sources', ['--provider' => ['sentinel2'], '--municipality' => ['1']])->assertSuccessful();
        $this->assertCount(1, Http::recorded(fn ($request) => str_contains($request->url(), 'statistics/v1')));
    }

    public static function changedPlotContexts(): array
    {
        return ['boundary' => ['boundary'], 'owner' => ['owner']];
    }

    private function weather(array $ids, ?array $forecast = null): void
    {
        $mock = Mockery::mock(WeatherForecastService::class);
        foreach ($ids as $id) {
            $mock->shouldReceive('forMunicipalityAt')->once()->ordered()
                ->withArgs(fn (Municipality $municipality, array $coordinates, bool $force) => $municipality->id === $id && $force)
                ->andReturn($forecast ?? $this->forecast());
        }
        if ($ids === []) {
            $mock->shouldNotReceive('forMunicipalityAt');
        }
        $this->app->instance(WeatherForecastService::class, $mock);
    }

    private function forecast(): array
    {
        $daily = [];
        for ($i = 0; $i < 7; $i++) {
            $daily[] = ['date' => now()->addDays($i)->toDateString(), 'precipitation_sum' => 2, 'temperature_max' => 36];
        }

        return ['available' => true, 'is_stale' => false, 'fetched_at' => now()->toIso8601String(), 'daily' => $daily,
            'summary' => ['seven_day_rain' => 14, 'maximum_temperature' => 36, 'maximum_rain_probability' => 20]];
    }

    private function schema(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('province_id');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->boolean('visitor_mode')->default(false);
        });
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
        });
        Schema::create('farm_plots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('farmer_id');
            $table->json('polygon_json')->nullable();
        });
        Schema::create('climate_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
        });
        Schema::create('municipality_boundaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('status');
            $table->double('centroid_lat');
            $table->double('centroid_lng');
        });
        (require database_path('migrations/2026_10_15_000300_create_climate_risk_source_readings.php'))->up();
        DB::table('provinces')->insert(['id' => 1, 'name' => 'Synthetic province']);
        foreach (range(1, 8) as $id) {
            DB::table('municipalities')->insert(['id' => $id, 'name' => 'Synthetic area '.$id, 'province_id' => 1, 'is_active' => $id !== 8]);
            DB::table('municipality_boundaries')->insert(['municipality_id' => $id, 'status' => 'active', 'centroid_lat' => 15.5, 'centroid_lng' => 120.5]);
        }
        DB::table('farmers')->insert(['municipality_id' => 1]);
        DB::table('users')->insert([
            ['municipality_id' => 2, 'role' => 'municipal_staff', 'is_active' => true, 'visitor_mode' => false],
            ['municipality_id' => 5, 'role' => 'municipal_staff', 'is_active' => false, 'visitor_mode' => false],
            ['municipality_id' => 6, 'role' => 'municipal_staff', 'is_active' => true, 'visitor_mode' => true],
            ['municipality_id' => 7, 'role' => 'gis_evaluator', 'is_active' => true, 'visitor_mode' => false],
        ]);
        DB::table('climate_risk_assessments')->insert(['municipality_id' => 3]);
    }
}
