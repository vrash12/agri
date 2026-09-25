<?php

namespace Tests\Feature;

use App\Models\FarmPlot;
use App\Models\User;
use App\Support\SentinelParcel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParcelSatelliteTest extends TestCase
{
    private const TOKEN = 'https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token';

    private const STATS = 'https://sh.dataspace.copernicus.eu/statistics/v1';

    private const IMAGE = 'https://sh.dataspace.copernicus.eu/api/v1/process';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array', 'cache.default' => 'array', 'services.google_maps.key' => '',
            'session.idle_timeout' => 120,
            'sentinel.enabled' => true, 'sentinel.client_id' => 'synthetic-test-client', 'sentinel.client_secret' => 'synthetic-test-secret',
            'sentinel.daily_requests' => 100, 'sentinel.monthly_requests' => 2000]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Cache::flush();
        $this->schema();
        $this->fixtures();
        Http::preventStrayRequests();
    }

    public function test_page_is_explicitly_disabled_without_credentials_and_makes_no_provider_call(): void
    {
        config(['sentinel.enabled' => false]);
        $this->actingAs(User::find(1))->get(route('farm-plots.satellite.show', 1))->assertOk()
            ->assertSee('Satellite access needs setup')->assertSee('Satellite observations')->assertDontSee('synthetic-test-secret');
        $this->postJson($this->url(), $this->period())->assertStatus(503)->assertJsonPath('message',
            'Satellite access is not configured. Ask the system administrator to enable Copernicus access.');
        Http::assertNothingSent();
    }

    public function test_modal_view_explains_ndvi_in_plain_language_and_keeps_scope_authorization(): void
    {
        $this->actingAs(User::find(1))->get(route('farm-plots.satellite.show', ['plot' => 1, 'modal' => 1]))
            ->assertOk()
            ->assertSee('What this shows')
            ->assertSee('vegetation greenness score called NDVI')
            ->assertSee('Use the result to decide where a field check may help')
            ->assertSee('Sentinel-2 images')
            ->assertDontSee('Back to parcel map');

        $this->actingAs(User::find(2))->get(route('farm-plots.satellite.show', ['plot' => 1, 'modal' => 1]))
            ->assertForbidden();
    }

    public function test_guests_cannot_read_pages_statistics_or_images(): void
    {
        $this->getJson(route('farm-plots.satellite.show', 1))->assertUnauthorized();
        $this->postJson($this->url(), $this->period())->assertUnauthorized();
        $this->getJson($this->imageUrl())->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_other_municipality_vet_evaluator_and_inactive_accounts_are_denied_before_provider_access(): void
    {
        foreach ([2, 5, 7, 8] as $id) {
            $this->actingAs(User::find($id))->getJson(route('farm-plots.satellite.show', 1))->assertForbidden();
            $this->postJson($this->url(), $this->period())->assertForbidden();
            $this->getJson($this->imageUrl())->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_provincial_staff_and_oversight_can_read_only_their_existing_scope(): void
    {
        $this->fake();
        foreach ([3, 4, 6] as $id) {
            $this->actingAs(User::find($id))->postJson($this->url(), $this->period())->assertOk();
        }
        $this->actingAs(User::find(3))->postJson($this->url(2), $this->period())->assertForbidden();
        Http::assertSentCount(2); // One authentication and one cached statistical request.
    }

    public function test_zero_negative_and_masked_values_are_distinct_and_requests_send_only_geometry(): void
    {
        $this->fake(['status' => 'OK', 'data' => [$this->entry('2026-08-03', 0, 100), $this->entry('2026-08-01', 0), $this->entry('2026-08-02', -0.2)]]);
        $before = FarmPlot::find(1)->getAttributes();
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertOk()
            ->assertJsonCount(3, 'observations')->assertJsonPath('observations.0.mean', 0)
            ->assertJsonPath('observations.1.mean', -0.2)->assertJsonPath('observations.2.mean', null)
            ->assertJsonPath('observations.2.status', 'no_data')->assertJsonPath('observations.2.valid_pixels', 0);
        Http::assertSent(function ($request) {
            if ($request->url() !== self::STATS) {
                return false;
            }
            $this->assertSame('sentinel-2-l2a', $request['input']['data'][0]['type']);
            $this->assertSame(60, $request['input']['data'][0]['dataFilter']['maxCloudCoverage']);
            $this->assertSame('leastCC', $request['input']['data'][0]['dataFilter']['mosaickingOrder']);
            $this->assertSame('2026-08-04T00:00:00Z', $request['aggregation']['timeRange']['to']);
            $this->assertSame('P1D', $request['aggregation']['aggregationInterval']['of']);
            $ring = $request['input']['bounds']['geometry']['coordinates'][0];
            $this->assertEquals([120.6, 15.7], $ring[0]);
            $this->assertSame($ring[0], $ring[count($ring) - 1]);
            $this->assertStringNotContainsString('Farmer', $request->body());
            $this->assertStringNotContainsString('municipality_id', $request->body());
            $this->assertStringNotContainsString('synthetic-test-secret', $request->body());

            return $request['aggregation']['resx'] < 0.001 && $request['aggregation']['resy'] < 0.001;
        });
        $this->assertSame($before, FarmPlot::find(1)->getAttributes());
        $this->assertDatabaseHas('audit_logs', ['module' => 'Satellite observations', 'event' => 'viewed', 'municipality_id' => 1]);
    }

    public function test_period_cloud_and_layer_validation_rejects_unbounded_requests(): void
    {
        $this->actingAs(User::find(1));
        foreach ([['to' => '2026-09-01'], ['from' => '2026-08-04'], ['from' => '2015-01-01'],
            ['to' => now()->addDay()->toDateString()], ['max_cloud' => 101], ['max_cloud' => 2.5], ['from' => 'not-a-date']] as $change) {
            Cache::flush();
            $this->postJson($this->url(), array_replace($this->period(), $change))->assertUnprocessable();
        }
        $this->getJson($this->imageUrl(['layer' => 'arbitrary-script']))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_invalid_huge_and_self_intersecting_parcels_never_reach_the_provider(): void
    {
        $this->actingAs(User::find(1));
        foreach ([[], array_fill(0, 501, ['lat' => 15, 'lng' => 120]),
            [['lat' => 15, 'lng' => 120], ['lat' => 16, 'lng' => 120], ['lat' => 16, 'lng' => 121]],
            [['lat' => 15, 'lng' => 120], ['lat' => 15.001, 'lng' => 120.001], ['lat' => 15.001, 'lng' => 120], ['lat' => 15, 'lng' => 120.001]]] as $polygon) {
            FarmPlot::find(1)->update(['polygon_json' => $polygon]);
            $this->postJson($this->url(), $this->period())->assertUnprocessable()->assertJsonValidationErrors('parcel');
        }
        Http::assertNothingSent();
    }

    public function test_cache_is_invalidated_by_geometry_filter_or_credentials_and_authorization_is_rechecked(): void
    {
        $this->fake();
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertOk();
        $this->postJson($this->url(), $this->period())->assertOk();
        Http::assertSentCount(2);
        $plot = FarmPlot::find(1);
        $points = $plot->polygon_json;
        $points[1]['lng'] += 0.0001;
        $plot->update(['polygon_json' => $points]);
        $this->postJson($this->url(), $this->period())->assertOk();
        $this->postJson($this->url(), array_replace($this->period(), ['max_cloud' => 100]))->assertOk();
        Http::assertSentCount(4);
        config(['sentinel.client_secret' => 'rotated-synthetic-secret']);
        $this->postJson($this->url(), $this->period())->assertOk();
        Http::assertSentCount(6);
        DB::table('farmers')->where('id', 1)->update(['municipality_id' => 2]);
        $this->postJson($this->url(), $this->period())->assertForbidden();
        Http::assertSentCount(6);
    }

    public function test_local_daily_and_monthly_allowances_allow_cache_hits_but_block_new_processing(): void
    {
        $this->fake();
        config(['sentinel.daily_requests' => 1]);
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertOk();
        $this->postJson($this->url(), $this->period())->assertOk();
        $this->getJson($this->imageUrl())->assertStatus(429);
        config(['sentinel.daily_requests' => 100, 'sentinel.monthly_requests' => 1]);
        $this->getJson($this->imageUrl())->assertStatus(429);
        Http::assertSentCount(2);
    }

    public function test_expired_oauth_tokens_are_refreshed_before_a_different_request(): void
    {
        $this->fake();
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertOk();
        $this->travel(61)->minutes();
        $this->postJson($this->url(), array_replace($this->period(), ['max_cloud' => 100]))->assertOk();
        Http::assertSentCount(4);
        $this->travelBack();
    }

    public function test_provider_refusal_is_safe_and_does_not_poison_cache(): void
    {
        Http::fake([self::TOKEN => Http::response(['access_token' => 'synthetic-test-token', 'expires_in' => 3600]),
            self::STATS => Http::sequence()->push(['message' => 'provider-private-details'], 401)->push($this->stats())]);
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertStatus(503)->assertDontSee('provider-private-details');
        $this->postJson($this->url(), $this->period())->assertOk();
        Http::assertSentCount(4);
    }

    public function test_provider_timeout_and_quota_are_clear_failures_not_empty_observations(): void
    {
        Http::fake([self::TOKEN => Http::response(['access_token' => 'synthetic-test-token', 'expires_in' => 3600]),
            self::STATS => function () {
            throw new ConnectionException('synthetic-sensitive-request');
            }]);
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertStatus(502)
            ->assertDontSee('synthetic-sensitive-request')->assertJsonMissingPath('observations');
    }

    public function test_provider_rate_limit_is_returned_without_automatic_paid_retries(): void
    {
        Http::fake([self::TOKEN => Http::response(['access_token' => 'synthetic-test-token', 'expires_in' => 3600]), self::STATS => Http::response([], 429)]);
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertStatus(429)->assertJsonMissingPath('observations');
        Http::assertSentCount(2);
    }

    public function test_failed_authentication_never_returns_secret_or_provider_response(): void
    {
        Http::fake([self::TOKEN => Http::response(['error' => 'provider-private-details synthetic-test-secret'], 400)]);
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertStatus(503)
            ->assertDontSee('provider-private-details')->assertDontSee('synthetic-test-secret');
        Http::assertSentCount(1);
    }

    /** @dataProvider invalidStatistics */
    public function test_malformed_provider_values_are_rejected(array $change): void
    {
        $entry = $this->entry('2026-08-01', 0.6);
        $entry['outputs']['ndvi']['bands']['B0']['stats'] = array_replace($entry['outputs']['ndvi']['bands']['B0']['stats'], $change);
        $this->fake(['status' => 'OK', 'data' => [$entry]]);
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertStatus(502)->assertJsonMissingPath('observations');
    }

    public static function invalidStatistics(): array
    {
        return [[['mean' => null]], [['mean' => 3]], [['min' => 0.8]], [['noDataCount' => 101]],
            [['sampleCount' => 900000]], [['sampleCount' => 10.5]], [['max' => 'NaN']]];
    }

    public function test_duplicate_days_and_partial_processing_failures_are_not_displayed_as_complete(): void
    {
        $entry = $this->entry('2026-08-01', 0.6);
        Http::fake([self::TOKEN => Http::response(['access_token' => 'synthetic-test-token', 'expires_in' => 3600]),
            self::STATS => Http::sequence()->push(['status' => 'OK', 'data' => [$entry, $entry]])
                ->push(['status' => 'OK', 'data' => [['error' => ['type' => 'EXECUTION_ERROR']]]])]);
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertStatus(502);
        $this->postJson($this->url(), $this->period())->assertStatus(502);
    }

    public function test_empty_period_is_a_success_with_no_invented_values(): void
    {
        $this->fake(['status' => 'OK', 'data' => []]);
        $this->actingAs(User::find(1))->postJson($this->url(), $this->period())->assertOk()->assertJsonCount(0, 'observations');
    }

    public function test_png_is_private_clipped_and_uses_the_statistics_grid(): void
    {
        $parcel = app(SentinelParcel::class)->describe(FarmPlot::find(1));
        $png = $this->png($parcel['width'], $parcel['height']);
        $this->fake(null, $png);
        $response = $this->actingAs(User::find(1))->getJson($this->imageUrl())->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertSame($png, $response->getContent());
        $this->getJson($this->imageUrl())->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($parcel) {
            return $request->url() === self::IMAGE && $request['output']['width'] === $parcel['width']
                && $request['output']['height'] === $parcel['height'] && $request['input']['bounds']['geometry'] === $parcel['geometry']
                && $request['input']['data'][0]['dataFilter']['timeRange']['to'] === '2026-08-02T00:00:00Z';
        });
    }

    public function test_wrong_image_size_or_html_is_rejected_instead_of_served(): void
    {
        Http::fake([self::TOKEN => Http::response(['access_token' => 'synthetic-test-token', 'expires_in' => 3600]),
            self::IMAGE => Http::sequence()->push($this->png(1, 1), 200, ['Content-Type' => 'image/png'])
                ->push('<html>provider details</html>', 200, ['Content-Type' => 'image/png'])]);
        $this->actingAs(User::find(1))->getJson($this->imageUrl())->assertStatus(502);
        $this->getJson($this->imageUrl())->assertStatus(502)->assertDontSee('provider details');
    }

    public function test_request_throttling_still_applies_to_cached_results(): void
    {
        $this->fake();
        $this->actingAs(User::find(1));
        for ($i = 0; $i < 6; $i++) {
            $this->postJson($this->url(), $this->period())->assertOk();
        }
        $this->postJson($this->url(), $this->period())->assertStatus(429);
        Http::assertSentCount(2);
    }

    private function fake(?array $stats = null, string $png = ''): void
    {
        Http::fake([self::TOKEN => Http::response(['access_token' => 'synthetic-test-token', 'expires_in' => 3600]),
            self::STATS => Http::response($stats ?? $this->stats()), self::IMAGE => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
    }

    private function stats(): array
    {
        return ['status' => 'OK', 'data' => [$this->entry('2026-08-01', 0.6)]];
    }

    private function entry(string $date, float $mean, int $masked = 20): array
    {
        return ['interval' => ['from' => $date.'T00:00:00Z'], 'outputs' => ['ndvi' => ['bands' => ['B0' => ['stats' => ['sampleCount' => 100, 'noDataCount' => $masked, 'mean' => $masked === 100 ? 'NaN' : $mean, 'min' => $mean - 0.1, 'max' => $mean + 0.1]]]]]];
    }

    private function period(): array
    {
        return ['from' => '2026-08-01', 'to' => '2026-08-03', 'max_cloud' => 60];
    }

    private function url(int $id = 1): string
    {
        return route('farm-plots.satellite.analyse', $id);
    }

    private function imageUrl(array $changes = []): string
    {
        return route('farm-plots.satellite.image', array_replace(['plot' => 1, 'date' => '2026-08-01', 'max_cloud' => 60, 'layer' => 'ndvi'], $changes));
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function fixtures(): void
    {
        DB::table('provinces')->insert([['id' => 1, 'name' => 'Home', 'is_active' => true], ['id' => 2, 'name' => 'Other', 'is_active' => true]]);
        DB::table('municipalities')->insert([['id' => 1, 'name' => 'Own Municipality', 'province_id' => 1], ['id' => 2, 'name' => 'Other Municipality', 'province_id' => 2]]);
        foreach ([1 => [User::ROLE_MUNICIPAL_STAFF, 1, null], 2 => [User::ROLE_MUNICIPAL_STAFF, 2, null],
            3 => [User::ROLE_SUPER_ADMIN, null, 1], 4 => [User::ROLE_SYSTEM_OWNER, null, null], 5 => [User::ROLE_PROVINCIAL_VET, null, 1],
            6 => [User::ROLE_PROVINCIAL_STAFF, null, 1], 7 => [User::ROLE_GIS_EVALUATOR, null, 1], 8 => [User::ROLE_MUNICIPAL_STAFF, 1, null]] as $id => [$role, $municipality, $province]) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Test Staff', 'email' => 'test'.$id.'@example.test', 'password' => 'unused',
                'role' => $role, 'municipality_id' => $municipality, 'province_id' => $province, 'is_active' => $id !== 8]);
        }
        DB::table('farmers')->insert([['id' => 1, 'municipality_id' => 1, 'first_name' => 'Sample', 'last_name' => 'Farmer'], ['id' => 2, 'municipality_id' => 2, 'first_name' => 'Other', 'last_name' => 'Farmer']]);
        foreach ([1, 2] as $id) {
            FarmPlot::create(['id' => $id, 'farmer_id' => $id, 'name' => 'Test parcel '.$id, 'color' => '#123456', 'area_ha' => 1.2,
                'polygon_json' => [['lat' => 15.7, 'lng' => 120.6], ['lat' => 15.7, 'lng' => 120.602], ['lat' => 15.702, 'lng' => 120.602], ['lat' => 15.702, 'lng' => 120.6]]]);
        }
    }

    private function schema(): void
    {
        Schema::create('provinces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('province')->nullable();
            $t->unsignedBigInteger('province_id');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->string('role');
            $t->unsignedBigInteger('municipality_id')->nullable();
            $t->unsignedBigInteger('province_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('farmers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->string('first_name');
            $t->string('last_name');
            $t->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('farmer_id');
            $t->string('name');
            $t->string('color');
            $t->json('polygon_json');
            $t->decimal('area_ha', 15, 4);
            $t->timestamps();
        });
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
    }
}
