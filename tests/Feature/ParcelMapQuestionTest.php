<?php

namespace Tests\Feature;

use App\Models\FarmPlot;
use App\Models\User;
use App\Modules\Climate\Application\ClimateEvidenceContext;
use App\Modules\Climate\Application\ParcelSatelliteHistory;
use App\Modules\Climate\Application\SatelliteIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Synthetic fixtures only: municipality 1 is the account's, municipality 2 is foreign. */
class ParcelMapQuestionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'services.google_maps.key' => '', 'session.idle_timeout' => 120]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 0));
        $this->schema();
        $this->fixtures();
    }

    public function test_corn_no_harvest_question_counts_only_own_unharvested_parcels_and_returns_a_highlight(): void
    {
        $this->season(1, 1, 'corn', 2026, 'wet');
        $this->season(2, 1, 'corn', 2026, 'wet');
        $this->season(3, 1, 'rice', 2026, 'wet');
        $this->season(4, 2, 'corn', 2026, 'wet'); // foreign municipality
        DB::table('harvest_records')->insert(['municipality_id' => 1, 'farmer_id' => 1, 'farm_plot_id' => 2, 'commodity' => 'corn', 'season' => 'wet', 'harvest_year' => 2026]);

        $response = $this->ask('corn parcels with no harvest this wet season')->assertOk()
            ->assertJsonPath('status', 'answered')->assertJsonPath('counts.matched', 1)
            ->assertJsonPath('action.type', 'highlight')->assertJsonPath('action.match.filter', 'no_harvest')
            ->assertJsonPath('action.match.crop', 'corn')->assertJsonPath('action.match.season', 'wet');
        $this->assertStringContainsString('1 corn parcel', $response->json('answer'));
        $this->assertStringNotContainsString('Foreign', $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->getJson($this->matchUrl(['filter' => 'no_harvest', 'crop' => 'corn', 'year' => 2026, 'season' => 'wet', 'plot_ids' => [1, 2, 3, 4]]))
            ->assertOk()->assertJsonPath('matches', [1])->assertJsonPath('checked', 3);
        Http::assertNothingSent();
    }

    public function test_unlinked_harvest_note_ignores_foreign_municipality_stale_farmer_links(): void
    {
        $this->season(1, 1, 'corn', 2026, 'wet');
        DB::table('harvest_records')->insert(['municipality_id' => 2, 'farmer_id' => 1, 'commodity' => 'corn', 'season' => 'wet', 'harvest_year' => 2026]);
        $response = $this->ask('corn parcels with no harvest this wet season')->assertOk();
        $this->assertCount(1, $response->json('notes'));
        DB::table('harvest_records')->insert(['municipality_id' => 1, 'farmer_id' => 1, 'commodity' => 'corn', 'season' => 'wet', 'harvest_year' => 2026]);
        $this->assertCount(2, $this->ask('corn parcels with no harvest this wet season')->assertOk()->json('notes'));
    }

    public function test_crop_question_uses_the_existing_crop_filter_with_the_stated_period(): void
    {
        $this->season(1, 1, 'rice', 2026, 'dry');
        $this->season(3, 1, 'mixed', 2026, 'dry', [['crop' => 'corn'], ['crop' => 'rice']]);
        $this->season(4, 2, 'rice', 2026, 'dry');
        $this->ask('rice parcels 2026 dry season', ['year' => 2025, 'season' => 'wet'])->assertOk()
            ->assertJsonPath('action.type', 'crop_filter')->assertJsonPath('action.crop', 'rice')
            ->assertJsonPath('action.year', 2026)->assertJsonPath('action.season', 'dry')->assertJsonPath('counts.matched', 2);
    }

    public function test_boundary_and_parcel_coverage_questions_report_scoped_counts_and_registry_links(): void
    {
        DB::table('farm_plots')->insert(['id' => 9, 'farmer_id' => 1, 'name' => 'No boundary', 'color' => '#123456', 'polygon_json' => '[]', 'area_ha' => 0]);
        DB::table('farm_plots')->insert(['id' => 10, 'farmer_id' => 2, 'name' => 'Foreign no boundary', 'color' => '#123456', 'polygon_json' => '[]', 'area_ha' => 0]);
        $this->ask('parcels without a boundary')->assertOk()->assertJsonPath('counts.matched', 1)
            ->assertJsonPath('counts.farmers_without_parcel', 2)->assertJsonPath('action.type', 'none');

        $response = $this->ask('farmers without a parcel in San Isidro')->assertOk()
            ->assertJsonPath('status', 'answered')->assertJsonPath('counts.matched', 1)->assertJsonPath('counts.total', 2)
            ->assertJsonPath('interpretation.location', 'San Isidro');
        $this->assertStringContainsString('mapping=unmapped', $response->json('links.0.url'));
    }

    public function test_unknown_or_foreign_place_clarifies_instead_of_widening(): void
    {
        // “Malaya” exists only in the foreign municipality.
        foreach (['farmers without a parcel in Malaya', 'farmers without a parcel in Atlantis'] as $question) {
            $this->ask($question)->assertOk()->assertJsonPath('status', 'clarify')
                ->assertJsonPath('counts', null)->assertJsonPath('action.type', 'none');
        }
    }

    public function test_unsupported_requests_clarify_with_examples(): void
    {
        foreach (['delete corn parcels', 'largest rice parcels', 'parcels larger than 2 hectares', 'compare 2025 and 2026 rice',
            'fish parcels', 'rice and corn parcels', 'parcels with pests', 'show me parcels', 'rice parcels 1800', 'parcels owned by women'] as $question) {
            $this->ask($question)->assertOk()->assertJsonPath('status', 'clarify')->assertJsonPath('action.type', 'none')
                ->assertJsonCount(5, 'examples');
        }
        $this->ask(str_repeat('a', 201))->assertUnprocessable();
    }

    public function test_workspace_filter_is_respected_and_cannot_be_widened(): void
    {
        $this->season(1, 1, 'rice', 2026, 'dry');
        $this->season(4, 2, 'rice', 2026, 'dry');
        // The provincial account sees both municipalities; a municipality filter narrows the count.
        $this->ask('rice parcels 2026 dry season', [], 6)->assertJsonPath('counts.matched', 1);
        DB::table('municipalities')->where('id', 2)->update(['province_id' => 1]);
        $this->ask('rice parcels 2026 dry season', [], 6)->assertJsonPath('counts.matched', 2);
        $this->postJson(route('farm-plots.map-questions', ['municipality_id' => 1]), $this->payload('rice parcels 2026 dry season'))
            ->assertOk()->assertJsonPath('counts.matched', 1);
        // A municipal account cannot select a foreign municipality.
        $this->actingAs(User::findOrFail(1))->getJson($this->matchUrl(['filter' => 'crop', 'crop' => 'rice', 'year' => 2026, 'season' => 'dry', 'plot_ids' => [4]]))
            ->assertOk()->assertJsonPath('matches', [])->assertJsonPath('checked', 0);
    }

    public function test_vet_evaluator_visitor_and_guests_are_denied(): void
    {
        foreach ([5, 7, 9] as $id) {
            $this->actingAs(User::findOrFail($id));
            $this->postJson(route('farm-plots.map-questions'), $this->payload('rice parcels'))->assertForbidden();
            $this->getJson($this->priorityUrl([1]))->assertForbidden();
            $this->getJson($this->matchUrl(['filter' => 'crop', 'year' => 2026, 'season' => 'dry', 'plot_ids' => [1]]))->assertForbidden();
        }
        auth()->logout();
        $this->postJson(route('farm-plots.map-questions'), $this->payload('rice parcels'))->assertUnauthorized();
    }

    public function test_batches_are_bounded_and_throttles_are_registered(): void
    {
        $this->actingAs(User::findOrFail(1))->getJson($this->priorityUrl(range(1, 201)))->assertUnprocessable();
        $this->getJson($this->matchUrl(['filter' => 'crop', 'year' => 2026, 'season' => 'dry', 'plot_ids' => range(1, 201)]))->assertUnprocessable();
        $this->getJson($this->matchUrl(['filter' => 'raw_sql', 'year' => 2026, 'season' => 'dry', 'plot_ids' => [1]]))->assertUnprocessable();
        $this->assertContains('throttle:map-questions', Route::getRoutes()->getByName('farm-plots.map-questions')->gatherMiddleware());
        $this->assertContains('synchronized', Route::getRoutes()->getByName('farm-plots.map-questions')->excludedMiddleware());
        $this->assertContains('throttle:map-layer-batches', Route::getRoutes()->getByName('farm-plots.map-matches')->gatherMiddleware());
        $this->assertContains('throttle:map-layer-batches', Route::getRoutes()->getByName('farm-plots.visit-priority')->gatherMiddleware());
        for ($i = 0; $i < 20; $i++) {
            $this->postJson(route('farm-plots.map-questions'), $this->payload('parcels without a boundary'))->assertOk();
        }
        $this->postJson(route('farm-plots.map-questions'), $this->payload('parcels without a boundary'))->assertStatus(429);
    }

    public function test_priority_flags_each_reason_from_saved_data_without_provider_calls(): void
    {
        $plot = FarmPlot::with('farmer')->findOrFail(1);
        DB::table('parcel_field_assessments')->insert([
            ['farm_plot_id' => 1, 'farmer_id' => 1, 'municipality_id' => 1, 'crop_year' => 2026, 'season' => 'wet', 'crop' => 'rice',
                'observed_on' => '2026-09-01', 'context_fingerprint' => ClimateEvidenceContext::plot($plot),
                'evidence' => json_encode(['follow_up_status' => 'open', 'next_inspection_on' => '2026-10-01'])],
            // A stale boundary context is never current evidence.
            ['farm_plot_id' => 3, 'farmer_id' => 3, 'municipality_id' => 1, 'crop_year' => 2026, 'season' => 'wet', 'crop' => 'rice',
                'observed_on' => '2026-09-01', 'context_fingerprint' => str_repeat('0', 64),
                'evidence' => json_encode(['follow_up_status' => 'open', 'next_inspection_on' => '2026-10-01'])],
        ]);
        $history = app(ParcelSatelliteHistory::class);
        $second = FarmPlot::with('farmer')->findOrFail(2);
        foreach ([['2026-10-05', 0.72], ['2026-10-15', 0.51]] as [$date, $mean]) {
            $history->remember($second, ['index' => 'ndvi', 'resolution_m' => SatelliteIndex::definition('ndvi')['resolution_m'],
                'scene_cloud_limit' => 60, 'observations' => [['date' => $date, 'mean' => $mean, 'valid_pixels' => 100, 'status' => 'available']]]);
        }
        $this->season(3, 1, 'rice', 2026, 'wet');
        $this->season(1, 1, 'rice', 2026, 'wet');
        DB::table('harvest_records')->insert(['municipality_id' => 1, 'farmer_id' => 1, 'farm_plot_id' => 1, 'commodity' => 'rice', 'season' => 'wet', 'harvest_year' => 2026]);
        $this->season(4, 2, 'rice', 2026, 'wet');

        $body = $this->actingAs(User::findOrFail(1))->getJson($this->priorityUrl([1, 2, 3, 4]))->assertOk()
            ->assertJsonPath('checked', 3)->json();
        $flags = collect($body['flagged'])->keyBy('plot_id');
        $this->assertSame(['visit_overdue'], array_column($flags[1]['reasons'], 'key'));
        $this->assertSame('high', $flags[1]['level']);
        $this->assertSame(['satellite_decline'], array_column($flags[2]['reasons'], 'key'));
        $this->assertSame(['no_harvest'], array_column($flags[3]['reasons'], 'key'));
        $this->assertSame('check', $flags[3]['level']);
        $this->assertArrayNotHasKey(4, $flags->all());
        $this->assertSame(['visit_overdue' => 1, 'satellite_decline' => 1, 'no_harvest' => 1], $body['counts']);
        $this->assertStringNotContainsString('polygon', json_encode($body));
        $this->assertStringContainsString('does not diagnose', $body['limits']);
        Http::assertNothingSent();

        $this->ask('parcels needing a field visit')->assertJsonPath('action.type', 'priority')->assertJsonPath('action.season', 'dry');
    }

    private function ask(string $question, array $context = [], int $user = 1)
    {
        return $this->actingAs(User::findOrFail($user))->postJson(route('farm-plots.map-questions'), $this->payload($question, $context));
    }

    private function payload(string $question, array $context = []): array
    {
        return ['question' => $question, 'year' => $context['year'] ?? 2026, 'season' => $context['season'] ?? 'dry'];
    }

    private function matchUrl(array $query): string
    {
        return route('farm-plots.map-matches', $query);
    }

    private function priorityUrl(array $ids): string
    {
        return route('farm-plots.visit-priority', ['year' => 2026, 'season' => 'wet', 'plot_ids' => $ids]);
    }

    private function season(int $plot, int $municipality, string $crop, int $year, string $season, ?array $areas = null): void
    {
        DB::table('parcel_crop_seasons')->insert(['farm_plot_id' => $plot, 'municipality_id' => $municipality, 'crop_year' => $year,
            'season' => $season, 'crop' => $crop, 'planted_areas' => $areas ? json_encode($areas) : null]);
    }

    private function schema(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
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
            $table->string('province')->nullable();
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
            $table->boolean('evaluation_password_pending')->default(false);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('farm_location')->nullable();
            $table->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farmer_id');
            $table->string('name');
            $table->string('color');
            $table->json('polygon_json')->nullable();
            $table->decimal('area_ha', 15, 4);
            $table->timestamps();
        });
        foreach (['2026_08_19_000300_create_audit_logs_table', '2026_09_19_000100_create_parcel_crop_seasons_table',
            '2026_10_08_000100_add_planted_areas_to_parcel_crop_seasons', '2026_09_19_000100_create_harvest_records_table',
            '2026_10_14_000100_link_harvest_records_to_crop_sections', '2026_10_19_000400_create_parcel_field_assessments_table'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
    }

    private function fixtures(): void
    {
        DB::table('regions')->insert(['id' => 1, 'name' => 'Synthetic Region', 'is_active' => true]);
        DB::table('provinces')->insert([['id' => 1, 'name' => 'Home', 'region_id' => 1], ['id' => 2, 'name' => 'Other', 'region_id' => null]]);
        DB::table('municipalities')->insert([['id' => 1, 'name' => 'Own Town', 'province' => 'Home', 'province_id' => 1],
            ['id' => 2, 'name' => 'Foreign Town', 'province' => 'Other', 'province_id' => 2]]);
        foreach ([1 => [User::ROLE_MUNICIPAL_STAFF, 1, null], 2 => [User::ROLE_MUNICIPAL_STAFF, 2, null],
            5 => [User::ROLE_PROVINCIAL_VET, null, 1], 6 => [User::ROLE_PROVINCIAL_STAFF, null, 1],
            7 => [User::ROLE_GIS_EVALUATOR, null, null], 9 => [User::ROLE_SUPER_ADMIN, null, 1]] as $id => [$role, $municipality, $province]) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Synthetic office '.$id, 'email' => 'office'.$id.'@example.test',
                'password' => 'unused', 'role' => $role, 'municipality_id' => $municipality, 'province_id' => $province,
                'visitor_mode' => $id === 9, 'evaluation_expires_at' => $id === 7 ? now()->addDay() : null]);
        }
        DB::table('farmers')->insert([
            ['id' => 1, 'municipality_id' => 1, 'first_name' => 'Synthetic', 'last_name' => 'One', 'farm_location' => 'SAN ISIDRO'],
            ['id' => 2, 'municipality_id' => 2, 'first_name' => 'Foreign', 'last_name' => 'Farmer', 'farm_location' => 'MALAYA'],
            ['id' => 3, 'municipality_id' => 1, 'first_name' => 'Synthetic', 'last_name' => 'Three', 'farm_location' => 'POBLACION'],
            ['id' => 4, 'municipality_id' => 1, 'first_name' => 'Synthetic', 'last_name' => 'Four', 'farm_location' => 'San Isidro'],
            ['id' => 5, 'municipality_id' => 1, 'first_name' => 'Synthetic', 'last_name' => 'Five', 'farm_location' => 'POBLACION'],
        ]);
        foreach ([1 => 1, 2 => 1, 3 => 3, 4 => 2] as $id => $farmer) {
            FarmPlot::query()->forceCreate(['id' => $id, 'farmer_id' => $farmer, 'name' => 'Synthetic parcel '.$id, 'color' => '#123456', 'area_ha' => 1.2,
                'polygon_json' => [['lat' => 15.68 + $id / 100, 'lng' => 120.62], ['lat' => 15.68 + $id / 100, 'lng' => 120.63], ['lat' => 15.69 + $id / 100, 'lng' => 120.63]]]);
        }
    }
}
