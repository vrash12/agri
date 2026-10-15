<?php

namespace Tests\Feature;

use App\Models\ClimateRiskAssessment;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\User;
use App\Modules\Climate\Application\ParcelVegetationSnapshot;
use App\Support\ConcurrentWrite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClimateRiskDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array', 'services.google_maps.key' => '', 'session.idle_timeout' => 120]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(8, 0));
        $this->schema();
        $this->fixtures();
    }

    public function test_empty_dashboard_uses_unassessed_and_never_calls_a_provider(): void
    {
        $this->actingAs(User::findOrFail(1))->get(route('climate-risk.index'))->assertOk()
            ->assertSee('El Niño readiness')->assertSee('Not assessed')->assertSee('No assessment has been recorded')
            ->assertSee('No parcels are reported affected')->assertDontSee('Foreign private farmer');
        $data = $this->getJson(route('climate-risk.map'))->assertOk();
        $data->assertJsonPath('features.0.properties.level', 'unassessed')->assertJsonPath('features.0.properties.affected_count', 0);
        Http::assertNothingSent();
    }

    public function test_dated_assessment_maps_the_area_and_explicit_parcels_only(): void
    {
        $this->actingAs(User::findOrFail(1))->post(route('climate-risk.store'), $this->data())->assertRedirect();
        $this->assertDatabaseCount('climate_risk_assessments', 1);
        $this->assertDatabaseHas('climate_risk_assessment_plots', ['farm_plot_id' => 1]);
        $this->get(route('climate-risk.index'))->assertOk()->assertSee('Test parcel 1')->assertSee('Office field review')->assertDontSee('Foreign private farmer');
        $data = $this->getJson(route('climate-risk.map'))->assertOk();
        $data->assertJsonPath('features.0.properties.level', 'alert')->assertJsonPath('features.0.properties.observed_on', '2026-10-14')
            ->assertJsonPath('features.0.properties.affected_count', 1)->assertJsonCount(1, 'parcels')->assertJsonPath('parcels.0.ndvi', null);
        $this->assertArrayNotHasKey('farmer', $data->json('parcels.0'));
        $this->assertArrayNotHasKey('notes', $data->json('features.0.properties'));
        $this->getJson(route('climate-risk.map'))->assertOk();
        Http::assertNothingSent();
        $audit = DB::table('audit_logs')->where('module', 'El Niño readiness')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('Private field note', json_encode($audit));
    }

    public function test_expired_assessments_do_not_keep_an_active_color_or_affected_count(): void
    {
        $this->actingAs(User::findOrFail(1))->post(route('climate-risk.store'), $this->data())->assertRedirect();
        $this->travel(8)->days();
        $this->withSession(['auth.last_activity_at' => now()->timestamp]);
        $this->getJson(route('climate-risk.map'))->assertOk()->assertJsonPath('features.0.properties.level', 'unassessed')
            ->assertJsonPath('features.0.properties.affected_count', 0)->assertJsonCount(0, 'parcels');
        $this->get(route('climate-risk.index'))->assertOk()->assertSee('Needs review')->assertSee('2026-10-14');
    }

    public function test_create_and_edit_forms_render_scope_and_record_versions(): void
    {
        $this->actingAs(User::findOrFail(1))->get(route('climate-risk.create'))->assertOk()
            ->assertSee('Record an assessment')->assertSee('Pance')->assertSee('Review by')->assertSee('source_url');
        $this->post(route('climate-risk.store'), $this->data())->assertRedirect();
        $record = ClimateRiskAssessment::firstOrFail();
        $this->get(route('climate-risk.edit', $record))->assertOk()->assertSee('Update assessment')
            ->assertSee(ConcurrentWrite::version($record))->assertSee('Test parcel 1')->assertSee('Withdraw this assessment');
        $this->actingAs(User::findOrFail(6))->get(route('climate-risk.create'))->assertOk()->assertSee('Choose an authorized municipality');
    }

    public function test_risk_filter_and_parcel_lookup_match_the_dashboard_scope(): void
    {
        $this->actingAs(User::findOrFail(1))->post(route('climate-risk.store'), $this->data())->assertRedirect();
        $this->getJson(route('climate-risk.map', ['risk' => 'normal']))->assertOk()->assertJsonCount(0, 'features');
        $this->getJson(route('climate-risk.parcels', ['q' => 'parcel']))->assertOk()->assertJsonCount(1, 'plots')->assertJsonPath('plots.0.id', 1);
        $this->getJson(route('climate-risk.parcels', ['q' => 'x']))->assertUnprocessable();
    }

    public function test_oversized_geometry_keeps_counts_and_parcel_links_without_an_oversized_map_payload(): void
    {
        $this->actingAs(User::findOrFail(1))->post(route('climate-risk.store'), $this->data())->assertRedirect();
        DB::table('farm_plots')->where('id', 1)->update(['polygon_json' => json_encode(array_fill(0, 20000, ['lat' => 15.68, 'lng' => 120.62]))]);
        DB::table('municipality_boundaries')->where('municipality_id', 1)->update(['geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [array_fill(0, 20000, [120.62, 15.68])]])]);
        $this->getJson(route('climate-risk.map'))->assertOk()->assertJsonPath('features.0.geometry', null)
            ->assertJsonPath('features.0.properties.affected_count', 1)->assertJsonPath('features.0.properties.center.lat', 15.68)
            ->assertJsonPath('parcels.0.path', null)->assertJsonPath('parcels.0.name', 'Test parcel 1');
    }

    public function test_baranguay_and_municipality_assessments_use_highest_current_level_and_count_a_parcel_once(): void
    {
        $this->actingAs(User::findOrFail(1))->post(route('climate-risk.store'), $this->data())->assertRedirect();
        $this->post(route('climate-risk.store'), $this->data(['barangay' => 'Pance', 'risk_level' => 'critical']))->assertRedirect();
        $result = $this->getJson(route('climate-risk.map'))->assertOk();
        $result->assertJsonPath('features.0.properties.level', 'critical')->assertJsonPath('features.0.properties.affected_count', 1)
            ->assertJsonPath('barangays.features.2.properties.name', 'Pance')->assertJsonPath('barangays.features.2.properties.level', 'critical')
            ->assertJsonPath('barangays.features.1.properties.level', 'unassessed');
    }

    #[DataProvider('deniedUsers')]
    public function test_restricted_roles_guests_and_inactive_users_cannot_read_the_module(?int $id): void
    {
        if ($id) {
            $this->actingAs(User::findOrFail($id));
        }
        $this->getJson(route('climate-risk.index'))->assertStatus($id ? 403 : 401);
        $this->getJson(route('climate-risk.map'))->assertStatus($id ? 403 : 401);
        $this->getJson(route('climate-risk.rainfall', ['municipality_id' => 1]))->assertStatus($id ? 403 : 401);
        Http::assertNothingSent();
    }

    public static function deniedUsers(): array
    {
        return ['guest' => [null], 'vet' => [5], 'evaluator' => [7], 'inactive' => [8], 'visitor' => [9]];
    }

    public function test_every_read_and_write_enforces_municipality_scope(): void
    {
        $this->actingAs(User::findOrFail(1))->post(route('climate-risk.store'), $this->data())->assertRedirect();
        $assessment = ClimateRiskAssessment::firstOrFail();
        $this->actingAs(User::findOrFail(2))->getJson(route('climate-risk.edit', $assessment))->assertForbidden();
        $this->getJson(route('climate-risk.map', ['municipality_id' => 1]))->assertNotFound();
        $this->getJson(route('climate-risk.rainfall', ['municipality_id' => 1]))->assertNotFound();
        $this->getJson(route('climate-risk.parcels', ['municipality_id' => 1, 'q' => 'parcel']))->assertNotFound();
        $this->putJson(route('climate-risk.update', $assessment), $this->data(['_record_version' => ConcurrentWrite::version($assessment)]))->assertForbidden();
        $this->actingAs(User::findOrFail(1))->postJson(route('climate-risk.store'), $this->data(['plot_ids' => [2]]))->assertUnprocessable()->assertJsonValidationErrors('plot_ids');
        $this->assertDatabaseCount('climate_risk_assessments', 1);
        Http::assertNothingSent();
    }

    public function test_regional_and_provincial_oversight_can_read_but_not_create_or_change_assessments(): void
    {
        foreach ([3, 10] as $id) {
            $this->actingAs(User::findOrFail($id))->getJson(route('climate-risk.map'))->assertOk()->assertJsonCount(1, 'features');
            $this->postJson(route('climate-risk.store'), $this->data())->assertForbidden();
        }
        $this->actingAs(User::findOrFail(4))->getJson(route('climate-risk.map'))->assertOk()->assertJsonCount(2, 'features');
        $this->postJson(route('climate-risk.store'), $this->data())->assertForbidden();
        $this->actingAs(User::findOrFail(6))->post(route('climate-risk.store'), $this->data())->assertRedirect();
        $this->postJson(route('climate-risk.store'), $this->data(['municipality_id' => 2]))->assertUnprocessable();
    }

    public function test_duplicate_create_and_stale_updates_do_not_overwrite_an_assessment(): void
    {
        $this->actingAs(User::findOrFail(1))->post(route('climate-risk.store'), $this->data())->assertRedirect();
        $this->postJson(route('climate-risk.store'), $this->data(['risk_level' => 'watch']))->assertUnprocessable()->assertJsonValidationErrors('barangay');
        $record = ClimateRiskAssessment::firstOrFail();
        $version = ConcurrentWrite::version($record);
        $this->put(route('climate-risk.update', $record), $this->data(['risk_level' => 'critical', '_record_version' => $version]))->assertRedirect();
        $this->putJson(route('climate-risk.update', $record), $this->data(['risk_level' => 'watch', '_record_version' => $version]))->assertUnprocessable()->assertJsonValidationErrors('_record_version');
        $this->assertSame('critical', $record->fresh()->risk_level);
        $this->delete(route('climate-risk.destroy', $record), ['_record_version' => ConcurrentWrite::version($record->fresh())])->assertRedirect();
        $this->assertDatabaseCount('climate_risk_assessments', 0);
        $this->assertDatabaseCount('climate_risk_assessment_plots', 0);
    }

    #[DataProvider('invalidAssessments')]
    public function test_invalid_dates_sources_levels_or_verification_are_rejected(array $changes, string $field): void
    {
        $this->actingAs(User::findOrFail(1))->postJson(route('climate-risk.store'), $this->data($changes))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('climate_risk_assessments', 0);
    }

    public static function invalidAssessments(): array
    {
        return [
            'future' => [['observed_on' => '2026-10-15'], 'observed_on'],
            'expiry' => [['valid_until' => '2026-12-01'], 'valid_until'],
            'fake level' => [['risk_level' => 'super_el_nino'], 'risk_level'],
            'unverified' => [['verified' => 0], 'verified'],
            'untrusted bulletin' => [['source_type' => 'pagasa', 'source_url' => 'https://example.test/bulletin'], 'source_url'],
            'credential URL' => [['source_url' => 'https://username:private@example.test/'], 'source_url'],
            'normal impact' => [['risk_level' => 'normal'], 'risk_level'],
        ];
    }

    public function test_transferred_parcels_disappear_from_the_original_offices_impact_list(): void
    {
        $this->actingAs(User::findOrFail(1))->post(route('climate-risk.store'), $this->data())->assertRedirect();
        DB::table('farmers')->where('id', 1)->update(['municipality_id' => 2]);
        $this->getJson(route('climate-risk.map'))->assertOk()->assertJsonCount(0, 'parcels')->assertJsonPath('features.0.properties.affected_count', 0);
    }

    public function test_vegetation_snapshots_keep_zero_observation_dates_and_fail_closed_after_geometry_or_ownership_changes(): void
    {
        $plot = FarmPlot::findOrFail(1);
        $snapshots = app(ParcelVegetationSnapshot::class);
        $snapshots->remember($plot, ['observations' => [['date' => '2026-10-12', 'mean' => 0, 'valid_pixels' => 80, 'status' => 'available']]]);
        $this->assertSame(0.0, $snapshots->forPlot($plot)['mean']);
        $this->assertFalse($snapshots->forPlot($plot)['is_stale']);
        $snapshots->remember($plot, ['observations' => [['date' => '2026-10-11', 'mean' => .6, 'valid_pixels' => 80, 'status' => 'available']]]);
        $this->assertSame('2026-10-12', $snapshots->forPlot($plot)['observed_on']);
        $snapshots->remember($plot, ['observations' => [['date' => '2026-10-14', 'mean' => null, 'valid_pixels' => 0, 'status' => 'no_data']]]);
        $this->assertSame('2026-10-12', $snapshots->forPlot($plot)['observed_on']);
        $this->travel(16)->days();
        $this->assertTrue($snapshots->forPlot($plot)['is_stale']);
        $plot->polygon_json = [['lat' => 15, 'lng' => 121], ['lat' => 15, 'lng' => 121.01], ['lat' => 15.01, 'lng' => 121]];
        $this->assertNull($snapshots->forPlot($plot));
        $plot = FarmPlot::findOrFail(1);
        $plot->setRelation('farmer', Farmer::findOrFail(2));
        $this->assertNull($snapshots->forPlot($plot));
        Http::assertNothingSent();
    }

    public function test_rainfall_uses_the_authorized_boundary_point_and_reuses_forecast_cache(): void
    {
        $dates = array_map(fn ($offset) => now()->addDays($offset)->toDateString(), range(0, 6));
        Http::fake(['api.open-meteo.com/*' => Http::response(['daily' => ['time' => $dates, 'precipitation_sum' => [0, 1, 2, 0, 0, 1, 0]]])]);
        $this->actingAs(User::findOrFail(1))->getJson(route('climate-risk.rainfall'))->assertOk()->assertJsonPath('coordinates.latitude', 15.68)->assertJsonCount(7, 'daily');
        $this->getJson(route('climate-risk.rainfall'))->assertOk();
        Http::assertSentCount(1);
        $this->getJson(route('climate-risk.map'))->assertOk();
        Http::assertSentCount(1);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['municipality_id' => 1, 'risk_level' => 'alert', 'drought_status' => 'dry_spell', 'rainfall_outlook' => 'below_normal',
            'observed_on' => '2026-10-14', 'valid_until' => '2026-10-21', 'source_type' => 'field', 'source_label' => 'Office inspection 2026-10-14',
            'notes' => 'Private field note excluded from audit', 'verified' => 1, 'plot_ids' => [1]], $changes);
    }

    private function schema(): void
    {
        Schema::create('regions', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->boolean('is_active');
        });
        Schema::create('provinces', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('region_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('province');
            $t->string('code');
            $t->unsignedBigInteger('province_id');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->string('role');
            $t->unsignedBigInteger('municipality_id')->nullable();
            $t->unsignedBigInteger('province_id')->nullable();
            $t->unsignedBigInteger('region_id')->nullable();
            $t->boolean('visitor_mode')->default(false);
            $t->dateTime('evaluation_expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('farmers', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->string('first_name');
            $t->string('last_name');
            $t->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('farmer_id');
            $t->string('name');
            $t->json('polygon_json');
            $t->string('color');
            $t->decimal('area_ha', 15, 4);
            $t->timestamps();
        });
        Schema::create('municipality_boundaries', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->string('status');
            $t->json('geojson');
            $t->double('centroid_lat');
            $t->double('centroid_lng');
            $t->timestamps();
        });
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        (require database_path('migrations/2026_10_14_000200_create_climate_risk_assessments.php'))->up();
    }

    private function fixtures(): void
    {
        DB::table('regions')->insert(['id' => 1, 'name' => 'Synthetic Region', 'is_active' => true]);
        DB::table('provinces')->insert([['id' => 1, 'name' => 'Tarlac', 'region_id' => 1], ['id' => 2, 'name' => 'Other', 'region_id' => null]]);
        DB::table('municipalities')->insert([['id' => 1, 'name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => 1, 'code' => 'RAMOS'], ['id' => 2, 'name' => 'Foreign Municipality', 'province' => 'Other', 'province_id' => 2, 'code' => 'FOREIGN']]);
        foreach ([1 => [User::ROLE_MUNICIPAL_STAFF, 1, null], 2 => [User::ROLE_MUNICIPAL_STAFF, 2, null], 3 => [User::ROLE_SUPER_ADMIN, null, 1],
            4 => [User::ROLE_SYSTEM_OWNER, null, null], 5 => [User::ROLE_PROVINCIAL_VET, null, 1], 6 => [User::ROLE_PROVINCIAL_STAFF, null, 1],
            7 => [User::ROLE_GIS_EVALUATOR, null, null], 8 => [User::ROLE_MUNICIPAL_STAFF, 1, null], 9 => [User::ROLE_SUPER_ADMIN, null, null], 10 => [User::ROLE_REGIONAL_HEAD, null, null]] as $id => [$role, $municipality, $province]) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Synthetic office '.$id, 'email' => 'office'.$id.'@example.test', 'password' => 'unused', 'role' => $role,
                'municipality_id' => $municipality, 'province_id' => $province, 'region_id' => $id === 10 ? 1 : null, 'visitor_mode' => $id === 9,
                'evaluation_expires_at' => $id === 7 ? now()->addDay() : null, 'is_active' => $id !== 8]);
        }
        DB::table('farmers')->insert([['id' => 1, 'municipality_id' => 1, 'first_name' => 'Synthetic', 'last_name' => 'Farmer'], ['id' => 2, 'municipality_id' => 2, 'first_name' => 'Foreign private farmer', 'last_name' => 'Restricted']]);
        foreach ([1, 2] as $id) {
            FarmPlot::create(['id' => $id, 'farmer_id' => $id, 'name' => 'Test parcel '.$id, 'color' => '#123456', 'area_ha' => 1.2,
                'polygon_json' => [['lat' => 15.68, 'lng' => 120.62], ['lat' => 15.68, 'lng' => 120.63], ['lat' => 15.69, 'lng' => 120.63]]]);
            DB::table('municipality_boundaries')->insert(['municipality_id' => $id, 'status' => 'active', 'centroid_lat' => 15.68, 'centroid_lng' => 120.63,
                'geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [[[120.6, 15.6], [120.7, 15.6], [120.7, 15.7], [120.6, 15.6]]]])]);
        }
    }
}
