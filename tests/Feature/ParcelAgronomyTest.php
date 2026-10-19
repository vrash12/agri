<?php

namespace Tests\Feature;

use App\Models\FarmPlot;
use App\Models\ParcelFieldAssessment;
use App\Models\User;
use App\Modules\Climate\Application\ClimateEvidenceContext;
use App\Modules\Climate\Application\ParcelFieldAssessments;
use App\Support\ConcurrentWrite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParcelAgronomyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array', 'services.google_maps.key' => '', 'session.idle_timeout' => 120]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 0));
        $this->schema();
        $this->fixtures();
    }

    public function test_missing_evidence_stays_missing_and_page_reads_do_not_call_providers(): void
    {
        $this->actingAs(User::findOrFail(1))->get($this->url())->assertOk()
            ->assertViewHas('assessment', null)->assertViewHas('canEdit', true)
            ->assertViewHas('recordVersion', 'new')->assertViewHas('year', 2026)
            ->assertViewHas('suitabilityProfiles', fn ($profiles) => array_keys($profiles) === ['rice', 'corn', 'mungbean']);
        $this->assertDatabaseCount('parcel_field_assessments', 0);
        Http::assertNothingSent();
    }

    public function test_staff_save_optional_missing_inputs_and_append_follow_up_without_rewriting_parcel_or_old_observations(): void
    {
        $before = FarmPlot::findOrFail(1)->getAttributes();
        $this->actingAs(User::findOrFail(1))->post($this->url(), $this->payload() + ['municipality_id' => 2])->assertRedirect();
        $first = ParcelFieldAssessment::firstOrFail();
        $original = $first->getAttributes();
        $this->assertSame(1, $first->municipality_id);
        $this->assertNull($first->evidence['soil_ph']);
        $this->assertNull($first->evidence['slope_percent']);
        $this->post($this->url(), $this->payload([
            '_record_version' => ConcurrentWrite::version($first), 'follow_up_status' => 'completed',
            'follow_up_notes' => 'Private field visit note', 'soil_source' => 'Private soil notebook',
        ]))->assertRedirect();
        $this->assertDatabaseCount('parcel_field_assessments', 2);
        $this->assertSame($original, $first->fresh()->getAttributes());
        $this->assertSame($before, FarmPlot::findOrFail(1)->getAttributes());
        $this->get($this->url())->assertOk()
            ->assertViewHas('assessment', fn ($data) => $data['follow_up_status'] === 'completed' && $data['context_current'])
            ->assertViewHas('history', fn ($history) => $history->total() === 2);
        $audit = json_encode(DB::table('audit_logs')->where('module', 'Parcel field checks')->get());
        $this->assertStringNotContainsString('Private field visit note', $audit);
        $this->assertStringNotContainsString('Private soil notebook', $audit);
        $this->assertDatabaseHas('audit_logs', ['module' => 'Parcel field checks', 'event' => 'recorded']);
        Http::assertNothingSent();
    }

    public function test_numeric_zero_is_preserved_with_required_measurement_provenance(): void
    {
        $this->actingAs(User::findOrFail(1))->post($this->url(), $this->payload([
            'slope_percent' => 0, 'terrain_source' => 'Survey', 'terrain_observed_on' => '2026-10-18',
            'growing_rainfall_mm' => 0, 'climate_source' => 'Growing-period gauge',
            'climate_period_start' => '2026-07-01', 'climate_period_end' => '2026-09-30',
        ]))->assertRedirect();
        $evidence = ParcelFieldAssessment::firstOrFail()->evidence;
        $this->assertEquals(0, $evidence['slope_percent']);
        $this->assertEquals(0, $evidence['growing_rainfall_mm']);
        $this->assertNull($evidence['soil_ph']);
    }

    #[DataProvider('invalidEvidence')]
    public function test_invalid_or_unsourced_evidence_is_rejected(array $changes, array $errors): void
    {
        $this->actingAs(User::findOrFail(1))->postJson($this->url(), $this->payload($changes))
            ->assertUnprocessable()->assertJsonValidationErrors($errors);
        $this->assertDatabaseCount('parcel_field_assessments', 0);
    }

    public static function invalidEvidence(): array
    {
        return [
            'soil requires source and date' => [['soil_ph' => 6.5], ['soil_source', 'soil_observed_on']],
            'zero slope requires provenance' => [['slope_percent' => 0], ['terrain_source', 'terrain_observed_on']],
            'climate requires full period' => [['growing_temperature_c' => 27], ['climate_source', 'climate_period_start', 'climate_period_end']],
            'water verification required' => [['water_availability' => 'reliable'], ['water_source', 'water_observed_on']],
            'future observation' => [['observed_on' => '2026-10-20'], ['observed_on']],
            'invalid calendar date' => [['observed_on' => '2026-02-30'], ['observed_on']],
            'measurement after visit' => [['soil_ph' => 6.5, 'soil_source' => 'Lab', 'soil_observed_on' => '2026-10-19', 'observed_on' => '2026-10-18'], ['soil_observed_on']],
            'reverse climate dates' => [['climate_period_start' => '2026-09-30', 'climate_period_end' => '2026-09-01'], ['climate_period_end']],
            'short forecast is not climate reference' => [['growing_temperature_c' => 27, 'climate_source' => 'Short forecast', 'climate_period_start' => '2026-10-01', 'climate_period_end' => '2026-10-07'], ['climate_period_end']],
            'multi-year total is not growing period' => [['growing_rainfall_mm' => 3000, 'climate_source' => 'Multi-year total', 'climate_period_start' => '2023-01-01', 'climate_period_end' => '2026-01-01'], ['climate_period_end']],
            'future planting' => [['planted_on' => '2026-10-20'], ['planted_on']],
            'followup before visit' => [['next_inspection_on' => '2026-10-18'], ['next_inspection_on']],
            'completed without outcome' => [['follow_up_status' => 'completed'], ['follow_up_notes']],
            'invalid factors' => [['soil_ph' => 15, 'slope_percent' => -1, 'growing_temperature_c' => 100, 'growing_rainfall_mm' => -1], ['soil_ph', 'slope_percent', 'growing_temperature_c', 'growing_rainfall_mm']],
            'unsupported crop' => [['crop' => 'anything'], ['crop']],
            'oversized notes' => [['follow_up_notes' => str_repeat('x', 1001)], ['follow_up_notes']],
            'missing plot version' => [['_plot_version' => null], ['_plot_version']],
            'missing ownership context' => [['_context_fingerprint' => null], ['_context_fingerprint']],
        ];
    }

    public function test_latest_record_and_boundary_versions_prevent_lost_updates(): void
    {
        $payload = $this->payload();
        $this->actingAs(User::findOrFail(1))->post($this->url(), $payload)->assertRedirect();
        $this->postJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors('_record_version');
        $payload['_record_version'] = ConcurrentWrite::version(ParcelFieldAssessment::firstOrFail());
        DB::table('farm_plots')->where('id', 1)->update(['polygon_json' => json_encode([['lat' => 15.7, 'lng' => 120.7]])]);
        $this->postJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors('_plot_version');
        $this->get($this->url())->assertOk()->assertViewHas('assessment', fn ($row) => $row['context_current'] === false);
        $this->assertDatabaseCount('parcel_field_assessments', 1);
    }

    public function test_old_visit_cannot_replace_a_newer_visit_and_seasons_keep_independent_versions(): void
    {
        $this->actingAs(User::findOrFail(1))->post($this->url(), $this->payload())->assertRedirect();
        $version = ConcurrentWrite::version(ParcelFieldAssessment::firstOrFail());
        $this->postJson($this->url(), $this->payload(['observed_on' => '2026-10-18', '_record_version' => $version]))
            ->assertUnprocessable()->assertJsonValidationErrors('observed_on');
        $this->post($this->url(), $this->payload(['season' => 'wet', 'crop' => 'corn']))->assertRedirect();
        $this->get($this->url().'?year=2026&season=dry')->assertViewHas('assessment', fn ($row) => $row['crop'] === 'rice');
        $this->get($this->url().'?year=2026&season=wet')->assertViewHas('assessment', fn ($row) => $row['crop'] === 'corn');
        $this->assertDatabaseCount('parcel_field_assessments', 2);
    }

    public function test_owner_changes_do_not_expose_former_owner_observations_even_within_the_same_office(): void
    {
        $this->actingAs(User::findOrFail(1))->post($this->url(), $this->payload())->assertRedirect();
        DB::table('farm_plots')->where('id', 1)->update(['farmer_id' => 3]);
        $this->get($this->url())->assertOk()->assertViewHas('assessment', null)
            ->assertViewHas('history', fn ($rows) => $rows->total() === 0);
        DB::table('farmers')->where('id', 3)->update(['municipality_id' => 2]);
        $this->getJson($this->url())->assertForbidden();
        $this->actingAs(User::findOrFail(2))->get($this->url())->assertOk()->assertViewHas('assessment', null)
            ->assertViewHas('history', fn ($rows) => $rows->total() === 0);
    }

    public function test_service_rechecks_current_parent_after_stale_model_was_loaded(): void
    {
        $plot = FarmPlot::with('farmer')->findOrFail(1);
        $payload = $this->payload();
        DB::table('farmers')->where('id', 1)->update(['municipality_id' => 2]);
        try {
            app(ParcelFieldAssessments::class)->save($plot, User::findOrFail(1), $payload);
            $this->fail('A moved farmer must not receive a stale field check.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('_plot_version', $exception->errors());
        }
        $this->assertDatabaseCount('parcel_field_assessments', 0);
    }

    public function test_service_rechecks_a_revoked_account_before_writing(): void
    {
        $user = User::findOrFail(1);
        $plot = FarmPlot::findOrFail(1);
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        try {
            app(ParcelFieldAssessments::class)->save($plot, $user, $this->payload());
            $this->fail('A revoked account must not save through a previously loaded user model.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('parcel_field_assessments', 0);
        }
    }

    public function test_verified_factors_render_for_all_three_profiles_and_user_text_is_escaped(): void
    {
        $text = '<script>alert("field")</script>';
        $this->actingAs(User::findOrFail(1))->post($this->url(), $this->payload([
            'soil_ph' => 6.5, 'soil_texture' => 'loamy', 'drainage' => 'good',
            'soil_source' => $text, 'soil_observed_on' => '2026-10-18',
            'slope_percent' => 2, 'terrain_source' => 'Survey', 'terrain_observed_on' => '2026-09-01',
            'growing_temperature_c' => 27, 'growing_rainfall_mm' => 500,
            'climate_source' => 'Verified growing-period reference', 'climate_period_start' => '2026-06-01', 'climate_period_end' => '2026-09-30',
            'water_availability' => 'reliable', 'water_source' => 'Canal inspection', 'water_observed_on' => '2026-10-19',
            'crop_stage' => 'flowering', 'planted_on' => '2026-08-01', 'field_condition' => 'normal',
            'follow_up_notes' => $text,
        ]))->assertRedirect();
        $this->get($this->url())->assertOk()->assertDontSee($text, false)->assertSee(e($text), false)
            ->assertViewHas('suitabilityProfiles', fn ($profiles) => count($profiles) === 3)
            ->assertViewHas('assessment', fn ($data) => $data['water_availability'] === 'reliable' && $data['soil_ph'] === 6.5);
        Http::assertNothingSent();
    }

    public function test_oversight_can_read_without_editing_and_cross_scope_roles_cannot_access(): void
    {
        foreach ([3, 10] as $id) {
            $this->actingAs(User::findOrFail($id))->get($this->url())->assertOk()->assertViewHas('canEdit', false);
            $this->postJson($this->url(), $this->payload())->assertForbidden();
        }
        foreach ([2, 5, 7, 8, 9] as $id) {
            $this->actingAs(User::findOrFail($id))->getJson($this->url())->assertForbidden();
            $this->postJson($this->url(), $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('parcel_field_assessments', 0);
        Http::assertNothingSent();
    }

    public function test_guest_cannot_read_or_write_and_foreign_payload_identifiers_do_not_change_ownership(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
        $this->postJson($this->url(), $this->payload())->assertUnauthorized();
        $this->actingAs(User::findOrFail(1))->post($this->url(), $this->payload() + [
            'farm_plot_id' => 2, 'farmer_id' => 2, 'municipality_id' => 2, 'context_fingerprint' => str_repeat('f', 64),
        ])->assertRedirect();
        $row = ParcelFieldAssessment::firstOrFail();
        $this->assertSame(1, $row->farm_plot_id);
        $this->assertSame(1, $row->farmer_id);
        $this->assertSame(1, $row->municipality_id);
    }

    public function test_migration_is_additive_and_can_be_reversed_without_parent_changes(): void
    {
        $this->actingAs(User::findOrFail(1))->post($this->url(), $this->payload())->assertRedirect();
        $before = FarmPlot::findOrFail(1)->getAttributes();
        (require database_path('migrations/2026_10_19_000400_create_parcel_field_assessments_table.php'))->down();
        $this->assertFalse(Schema::hasTable('parcel_field_assessments'));
        $this->assertSame($before, FarmPlot::findOrFail(1)->getAttributes());
    }

    private function url(): string
    {
        return '/farm-plots/1/agronomy';
    }

    private function payload(array $changes = []): array
    {
        $plot = FarmPlot::with('farmer')->findOrFail(1);

        return array_replace([
            '_record_version' => 'new', '_plot_version' => ConcurrentWrite::version($plot),
            '_context_fingerprint' => ClimateEvidenceContext::plot($plot), 'crop' => 'rice', 'crop_year' => 2026,
            'season' => 'dry', 'observed_on' => '2026-10-19', 'crop_stage' => 'unknown',
            'field_condition' => 'unknown', 'water_availability' => 'unknown', 'follow_up_status' => 'open',
        ], $changes);
    }

    private function schema(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active');
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('region_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->string('code');
            $table->unsignedBigInteger('province_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->boolean('visitor_mode')->default(false);
            $table->dateTime('evaluation_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farmer_id');
            $table->string('name');
            $table->json('polygon_json');
            $table->string('color');
            $table->decimal('area_ha', 15, 4);
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        (require database_path('migrations/2026_10_19_000400_create_parcel_field_assessments_table.php'))->up();
    }

    private function fixtures(): void
    {
        DB::table('regions')->insert(['id' => 1, 'name' => 'Synthetic Region', 'is_active' => true]);
        DB::table('provinces')->insert([['id' => 1, 'name' => 'Tarlac', 'region_id' => 1], ['id' => 2, 'name' => 'Other', 'region_id' => null]]);
        DB::table('municipalities')->insert([
            ['id' => 1, 'name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => 1, 'code' => 'RAMOS'],
            ['id' => 2, 'name' => 'Foreign Municipality', 'province' => 'Other', 'province_id' => 2, 'code' => 'OTHER'],
        ]);
        foreach ([1 => [User::ROLE_MUNICIPAL_STAFF, 1, null], 2 => [User::ROLE_MUNICIPAL_STAFF, 2, null],
            3 => [User::ROLE_SUPER_ADMIN, null, 1], 4 => [User::ROLE_SYSTEM_OWNER, null, null],
            5 => [User::ROLE_PROVINCIAL_VET, null, 1], 6 => [User::ROLE_PROVINCIAL_STAFF, null, 1],
            7 => [User::ROLE_GIS_EVALUATOR, null, null], 8 => [User::ROLE_MUNICIPAL_STAFF, 1, null],
            9 => [User::ROLE_SUPER_ADMIN, null, null], 10 => [User::ROLE_REGIONAL_HEAD, null, null]] as $id => [$role, $municipality, $province]) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Synthetic office '.$id, 'email' => 'office'.$id.'@example.test',
                'password' => 'unused', 'role' => $role, 'municipality_id' => $municipality, 'province_id' => $province,
                'region_id' => $id === 10 ? 1 : null, 'visitor_mode' => $id === 9,
                'evaluation_expires_at' => $id === 7 ? now()->addDay() : null, 'is_active' => $id !== 8]);
        }
        DB::table('farmers')->insert([
            ['id' => 1, 'municipality_id' => 1, 'first_name' => 'Synthetic', 'last_name' => 'Farmer'],
            ['id' => 2, 'municipality_id' => 2, 'first_name' => 'Foreign', 'last_name' => 'Farmer'],
            ['id' => 3, 'municipality_id' => 1, 'first_name' => 'Another', 'last_name' => 'Farmer'],
        ]);
        foreach ([1, 2] as $id) {
            FarmPlot::create(['farmer_id' => $id, 'name' => 'Test parcel '.$id, 'color' => '#123456', 'area_ha' => 1.2,
                'polygon_json' => [['lat' => 15.68, 'lng' => 120.62], ['lat' => 15.68, 'lng' => 120.63], ['lat' => 15.69, 'lng' => 120.63]]]);
        }
    }
}
