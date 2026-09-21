<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MunicipalityGeofenceTest extends TestCase
{
    private Municipality $first;

    private Municipality $second;

    private User $superAdmin;

    private User $provincial;

    private User $municipal;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Cache::clear();
        $this->createSchema();
        $province = Province::create(['name' => 'Tarlac', 'is_active' => true]);

        $this->first = Municipality::create(['name' => 'Anao', 'province' => 'Tarlac', 'province_id' => $province->id, 'code' => 'ANA', 'is_active' => true]);
        $this->second = Municipality::create(['name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => $province->id, 'code' => 'RAM', 'is_active' => true]);
        $this->superAdmin = $this->user(User::ROLE_SUPER_ADMIN, null, 'super@example.test');
        $this->provincial = $this->user(User::ROLE_PROVINCIAL_STAFF, null, 'province@example.test');
        $this->municipal = $this->user(User::ROLE_MUNICIPAL_STAFF, $this->first->id, 'anao@example.test');
    }

    public function test_style_save_preserves_multipart_geometry_and_returns_same_values_to_both_maps(): void
    {
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);
        $boundary->update(['geojson' => ['type' => 'MultiPolygon', 'coordinates' => [$boundary->geojson['coordinates']]]]);
        $before = $boundary->fresh()->getAttributes();
        $this->actingAs($this->superAdmin)->patchJson(route('municipality-boundaries.style', $boundary), [
            'color' => '#ffffff', 'fill_opacity' => 0, '_record_version' => ConcurrentWrite::version($boundary->fresh()),
        ])->assertOk()->assertJsonPath('boundary.color', '#FFFFFF')->assertJsonPath('boundary.fill_opacity', 0);
        $after = $boundary->fresh();
        foreach (['geojson', 'municipality_id', 'name', 'status', 'area_ha', 'centroid_lat', 'centroid_lng', 'vertex_count'] as $field) {
            $this->assertSame($before[$field], $after->getAttributes()[$field]);
        }
        $this->actingAs($this->municipal)->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->first->id]))
            ->assertOk()->assertJsonFragment(['color' => '#FFFFFF', 'fill_opacity' => 0]);
        Schema::create('rice_seed_distributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->string('quantity_unit')->nullable();
            $table->decimal('kgs_received')->nullable();
            $table->date('date_received')->nullable();
        });
        Schema::table('farmers', fn (Blueprint $table) => $table->string('gender')->nullable());
        $position = app(\App\Support\GeoGeometry::class)->labelPosition($after->geojson);
        $this->get(route('farmers.index'))->assertOk()->assertViewHas('mapMunicipalityBoundaries', fn ($rows) => $rows->first()['color'] === '#FFFFFF' && $rows->first()['fill_opacity'] === 0.0 && $rows->first()['label_position'] === $position);
        $this->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->first->id]))->assertJsonFragment(['label_position' => $position]);
        $this->assertTrue(DB::table('audit_logs')->where('event', 'updated')->where('metadata', 'like', '%map_style%')->exists());
    }

    public function test_style_save_enforces_role_province_and_record_version(): void
    {
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);
        $payload = ['color' => '#FFFFFF', 'fill_opacity' => .4, '_record_version' => ConcurrentWrite::version($boundary)];
        $url = route('municipality-boundaries.style', $boundary);
        $this->actingAs($this->municipal)->patchJson($url, $payload)->assertForbidden();
        $this->actingAs($this->provincial)->patchJson($url, $payload)->assertForbidden();
        $foreign = Province::create(['name' => 'Other province', 'is_active' => true]);
        $this->superAdmin->update(['province_id' => $foreign->id]);
        $this->actingAs($this->superAdmin->fresh())->patchJson($url, $payload)->assertForbidden();
        $this->superAdmin->update(['province_id' => $this->first->province_id]);
        $boundary->update(['color' => '#123456']);
        $this->actingAs($this->superAdmin->fresh())->patchJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('_record_version');
        $this->assertSame('#123456', $boundary->fresh()->color);
    }

    public function test_style_save_rejects_invalid_values_geometry_and_archived_boundaries(): void
    {
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);
        $url = route('municipality-boundaries.style', $boundary);
        $payload = ['color' => '#FFFFFF', 'fill_opacity' => .2, '_record_version' => ConcurrentWrite::version($boundary)];
        foreach ([-.1, 1.1, 'bad', null, .123] as $opacity) {
            $this->actingAs($this->superAdmin)->patchJson($url, array_replace($payload, ['fill_opacity' => $opacity]))->assertUnprocessable()->assertJsonValidationErrors('fill_opacity');
        }
        $this->patchJson($url, array_replace($payload, ['color' => 'white']))->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->patchJson($url, $payload + ['geojson' => $boundary->geojson])->assertUnprocessable()->assertJsonValidationErrors('geojson');
        $boundary->update(['status' => 'archived']);
        $this->patchJson($url, array_replace($payload, ['_record_version' => ConcurrentWrite::version($boundary->fresh())]))->assertUnprocessable()->assertJsonValidationErrors('boundary');
    }

    public function test_style_save_accepts_drafts_and_solid_fill_without_mutating_status(): void
    {
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);
        $boundary->update(['status' => 'draft']);
        $this->actingAs($this->superAdmin)->patchJson(route('municipality-boundaries.style', $boundary), [
            'color' => '#ABCDEF', 'fill_opacity' => 1, '_record_version' => ConcurrentWrite::version($boundary->fresh()),
        ])->assertOk()->assertJsonPath('boundary.fill_opacity', 1)->assertJsonPath('boundary.status', 'draft');
    }

    public function test_only_super_admin_can_create_boundaries(): void
    {
        $payload = $this->boundaryPayload($this->first->id, 120.50, 15.40);

        $this->actingAs($this->provincial)
            ->postJson(route('municipality-boundaries.store'), $payload)
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->postJson(route('municipality-boundaries.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('boundary.status', 'active');

        $this->assertDatabaseHas('municipality_boundaries', [
            'municipality_id' => $this->first->id,
            'status' => MunicipalityBoundary::STATUS_ACTIVE,
        ]);
    }

    public function test_workspace_renders_management_controls_only_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('municipality-boundaries.index'))
            ->assertOk()
            ->assertSee('Draw municipality boundary')
            ->assertSee('Import KML, KMZ, or GeoJSON')
            ->assertSee('Download municipality snapshot')
            ->assertDontSee('$user->isProvincialVeterinaryOffice()', false);

        $this->actingAs($this->municipal)
            ->get(route('municipality-boundaries.index'))
            ->assertOk()
            ->assertDontSee('Draw municipality boundary')
            ->assertSee('Municipality geofences');
    }

    public function test_gis_evaluator_can_only_read_active_boundaries_without_operational_data(): void
    {
        $active = $this->createBoundary($this->first, 120.50, 15.40);
        $draft = $this->createBoundary($this->second, 120.60, 15.50);
        $draft->update(['status' => MunicipalityBoundary::STATUS_DRAFT, 'name' => 'Internal draft boundary']);
        $farmer = Farmer::create([
            'municipality_id' => $this->first->id,
            'first_name' => 'Protected',
            'last_name' => 'Farmer',
            'ffrs' => 'PROTECTED-FFRS',
        ]);
        $this->plot($farmer, 'Protected parcel', 120.505, 15.405);
        $evaluator = $this->user(User::ROLE_GIS_EVALUATOR, null, 'namria@example.test');
        $evaluator->forceFill(['evaluation_expires_at' => now()->addDays(30)])->save();

        $this->actingAs($evaluator)
            ->get(route('municipality-boundaries.index'))
            ->assertOk()
            ->assertSee('Restricted external evaluation')
            ->assertSee($active->name)
            ->assertDontSee('Internal draft boundary')
            ->assertDontSee('Registered farmers')
            ->assertDontSee('Download municipality snapshot');

        $this->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->first->id]))
            ->assertOk()
            ->assertJsonCount(0, 'parcels')
            ->assertJsonCount(0, 'review')
            ->assertJsonPath('stats.farmers', 0)
            ->assertJsonMissingPath('boundaries.0._record_version')
            ->assertJsonMissing(['name' => 'Protected Farmer'])
            ->assertJsonMissing(['ffrs' => 'PROTECTED-FFRS']);

        $this->getJson(route('dashboard'))->assertForbidden()->assertJsonPath('code', 'GIS_EVALUATOR_SCOPE_ONLY');
        $this->getJson(route('farmers.index'))->assertForbidden()->assertJsonPath('code', 'GIS_EVALUATOR_SCOPE_ONLY');
        $this->getJson(route('municipality-boundaries.snapshot-base', $active))->assertForbidden();
        $this->postJson(route('municipality-boundaries.store'), $this->boundaryPayload($this->first->id, 120.70, 15.60))
            ->assertForbidden();
        $this->assertFalse($evaluator->can('create', MunicipalityBoundary::class));
        $this->assertFalse($evaluator->can('view', $farmer));
        $this->assertSame(0, app(\App\Support\MunicipalityAccess::class)->scope(Farmer::query(), $evaluator)->count());
        $evaluator->forceFill(['evaluation_password_pending' => true])->save();
        $this->actingAs($evaluator)->get(route('municipality-boundaries.index'))->assertRedirect(route('evaluation.password'));
        $this->get(route('evaluation.password'))->assertOk();
        $this->post(route('evaluation.password.update'), [
            'current_password' => 'wrong', 'password' => 'synthetic-new-password-2026', 'password_confirmation' => 'synthetic-new-password-2026',
        ])->assertSessionHasErrors('current_password');
        $this->post(route('evaluation.password.update'), [
            'current_password' => 'password', 'password' => 'synthetic-new-password-2026', 'password_confirmation' => 'synthetic-new-password-2026',
        ])->assertRedirect(route('municipality-boundaries.index'));
        $this->assertFalse($evaluator->fresh()->evaluation_password_pending);
        $evaluator->forceFill(['evaluation_expires_at' => now()->subMinute()])->save();
        $this->actingAs($evaluator)->getJson(route('municipality-boundaries.index'))->assertForbidden();
        auth()->logout();
        $this->post(route('login.attempt'), ['email' => $evaluator->email, 'password' => 'synthetic-new-password-2026', 'confidentiality_acknowledged' => '1'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_municipal_roles_receive_only_their_assigned_workspace_without_municipality_search(): void
    {
        $ownBoundary = $this->createBoundary($this->first, 120.50, 15.40);
        $foreignBoundary = $this->createBoundary($this->second, 120.60, 15.50);
        $foreignBoundary->update(['name' => 'Foreign boundary detail']);
        $ownFarmer = Farmer::create(['municipality_id' => $this->first->id, 'first_name' => 'Own', 'last_name' => 'Farmer']);
        $foreignFarmer = Farmer::create(['municipality_id' => $this->second->id, 'first_name' => 'Foreign', 'last_name' => 'Farmer']);
        $ownPlot = $this->plot($ownFarmer, 'Own parcel', 120.505, 15.405);
        $this->plot($foreignFarmer, 'Foreign parcel detail', 120.605, 15.505);

        foreach ([User::ROLE_MUNICIPAL_STAFF, User::ROLE_MUNICIPAL_HEAD] as $role) {
            $this->municipal->role = $role;

            $this->actingAs($this->municipal)
                ->get(route('municipality-boundaries.index', ['municipality_id' => $this->second->id]))
                ->assertOk()
                ->assertSee('Assigned municipality')
                ->assertSee('Geofence color opacity')
                ->assertSee('id="geofenceOpacity" type="range" min="0" max="100" step="1" value="20"', false)
                ->assertSee($this->first->name)
                ->assertSee('Reset municipality view')
                ->assertSee('type="hidden" id="municipalityFilter" value="'.$this->first->id.'"', false)
                ->assertDontSee('Find municipality')
                ->assertDontSee('id="boundarySearch"', false)
                ->assertDontSee('<select id="municipalityFilter">', false)
                ->assertDontSee('All municipalities')
                ->assertDontSee('Reset province view')
                ->assertDontSee('Province-wide view')
                ->assertDontSee('Province boundary administration')
                ->assertDontSee('Use the municipality selector')
                ->assertDontSee($this->second->name)
                ->assertDontSee('Foreign boundary detail')
                ->assertViewHas('municipalities', fn ($items) => $items->pluck('id')->all() === [$this->first->id])
                ->assertViewHas('boundaries', fn ($items) => $items->pluck('id')->all() === [$ownBoundary->id])
                ->assertViewHas('summary', fn ($summary) => $summary['municipalities'] === 1 && $summary['farmers'] === 1 && $summary['parcels'] === 1);

            $this->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->first->id]))
                ->assertOk()
                ->assertJsonCount(1, 'boundaries')
                ->assertJsonPath('boundaries.0.id', $ownBoundary->id)
                ->assertJsonCount(1, 'parcels')
                ->assertJsonPath('parcels.0.id', $ownPlot->id)
                ->assertJsonPath('stats.farmers', 1)
                ->assertJsonMissing(['name' => 'Foreign parcel detail']);

            $this->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->second->id]))
                ->assertNotFound();
        }
    }

    public function test_provincial_roles_keep_municipality_search_and_province_overview(): void
    {
        foreach ([$this->provincial, $this->superAdmin] as $user) {
            $this->actingAs($user)
                ->get(route('municipality-boundaries.index'))
                ->assertOk()
                ->assertSee('Find municipality')
                ->assertSee('Geofence color opacity')
                ->assertSee('<select id="municipalityFilter">', false)
                ->assertSee('All municipalities')
                ->assertSee('Reset province view')
                ->assertSee('Province-wide view')
                ->assertSee($this->first->name)
                ->assertSee($this->second->name)
                ->assertViewHas('municipalities', fn ($items) => $items->count() === 2);
        }
    }

    public function test_super_admin_can_import_a_valid_geojson_draft(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'anao.geojson',
            json_encode($this->geoJsonSquare(120.50, 15.40, 0.02))
        );

        $this->actingAs($this->superAdmin)
            ->post(route('municipality-boundaries.import'), [
                'municipality_id' => $this->first->id,
                'name' => 'Imported Anao boundary',
                'color' => '#15803D',
                'status' => MunicipalityBoundary::STATUS_DRAFT,
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('boundary.status', MunicipalityBoundary::STATUS_DRAFT);
    }

    public function test_municipal_user_cannot_load_another_municipality_workspace(): void
    {
        $this->createBoundary($this->first, 120.50, 15.40);
        $this->createBoundary($this->second, 120.60, 15.50);

        $this->actingAs($this->municipal)
            ->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->first->id]))
            ->assertOk()
            ->assertJsonPath('municipality.id', $this->first->id);

        $this->actingAs($this->municipal)
            ->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->second->id]))
            ->assertNotFound();
    }

    public function test_overlapping_active_municipality_boundary_is_rejected(): void
    {
        $this->createBoundary($this->first, 120.50, 15.40, 0.02);

        $this->actingAs($this->superAdmin)
            ->postJson(route('municipality-boundaries.store'), $this->boundaryPayload($this->second->id, 120.51, 15.41, 0.02))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('geojson');
    }

    public function test_name_and_color_changes_preserve_saved_geometry_even_with_an_existing_conflict(): void
    {
        $boundary = $this->createBoundary($this->first, 120.50123456789, 15.40123456789);
        $neighbor = $this->createBoundary($this->second, 120.60, 15.50);
        $geometryFields = ['geojson', 'area_ha', 'centroid_lat', 'centroid_lng', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'vertex_count'];
        // Legacy/imported conflicts must not prevent harmless name or styling changes.
        $neighbor->forceFill($boundary->only($geometryFields))->save();
        $originalGeometry = $boundary->only($geometryFields);

        foreach ([[], ['geojson' => $boundary->geojson]] as $geometryInput) {
            $this->actingAs($this->superAdmin)
                ->putJson(route('municipality-boundaries.update', $boundary), $geometryInput + [
                    'name' => 'Reviewed boundary', 'color' => '#FACC15',
                    '_record_version' => ConcurrentWrite::version($boundary->refresh()),
                ])
                ->assertOk()->assertJsonPath('boundary.color', '#FACC15');
            $this->assertSame($originalGeometry, $boundary->refresh()->only($geometryFields));
        }
    }

    public function test_geometry_edits_still_reject_actual_overlap_and_require_confirmation(): void
    {
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);
        $this->createBoundary($this->second, 120.53, 15.40);
        $original = $boundary->getAttributes();
        $body = [
            'geojson' => $this->geoJsonSquare(120.525, 15.40, 0.02),
            '_record_version' => ConcurrentWrite::version($boundary),
        ];
        $this->actingAs($this->superAdmin)
            ->putJson(route('municipality-boundaries.update', $boundary), $body)
            ->assertUnprocessable()->assertJsonValidationErrors('replace_confirmed');
        $this->putJson(route('municipality-boundaries.update', $boundary), $body + ['replace_confirmed' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('geojson');
        $this->assertSame($original, $boundary->fresh()->getAttributes());
    }

    public function test_safe_geometry_edit_retains_shared_edge_and_updates_measurements(): void
    {
        $boundary = $this->createBoundary($this->first, 120.50123456789, 15.40123456789);
        $this->createBoundary($this->second, 120.52123456789, 15.40123456789);
        $geometry = $boundary->geojson;
        // Move the opposite edge; the shared border must remain exactly where it was.
        $geometry['coordinates'][0][0][0] -= 0.001;
        $geometry['coordinates'][0][3][0] -= 0.001;
        $geometry['coordinates'][0][4] = $geometry['coordinates'][0][0];
        $oldArea = $boundary->area_ha;
        $this->actingAs($this->superAdmin)
            ->putJson(route('municipality-boundaries.update', $boundary), [
                'geojson' => $geometry, 'replace_confirmed' => true,
                '_record_version' => ConcurrentWrite::version($boundary),
            ])->assertOk();
        $this->assertSame($geometry, $boundary->fresh()->geojson);
        $this->assertGreaterThan($oldArea, $boundary->fresh()->area_ha);
    }

    public function test_outside_parcel_is_blocked_and_partial_parcel_returns_warning(): void
    {
        $this->createBoundary($this->first, 120.50, 15.40, 0.02);
        $farmer = Farmer::create([
            'municipality_id' => $this->first->id,
            'first_name' => 'Test',
            'last_name' => 'Farmer',
        ]);

        $this->actingAs($this->municipal)
            ->postJson(route('farmers.plots.store', $farmer), [
                'name' => 'Outside parcel',
                'polygon' => $this->latLngRing(120.53, 15.43, 0.002),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('polygon');

        $this->actingAs($this->municipal)
            ->postJson(route('farmers.plots.store', $farmer), [
                'name' => 'Crossing parcel',
                'polygon' => $this->latLngRing(120.519, 15.409, 0.003),
            ])
            ->assertCreated()
            ->assertJsonPath('geofence.status', 'partial');
    }

    public function test_stale_boundary_update_is_rejected(): void
    {
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);
        $staleVersion = ConcurrentWrite::version($boundary);
        $boundary->update(['name' => 'Changed by another request']);

        $this->actingAs($this->superAdmin)
            ->putJson(route('municipality-boundaries.update', $boundary), [
                'name' => 'My stale change',
                '_record_version' => $staleVersion,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('_record_version');
    }

    public function test_map_data_contains_only_the_selected_municipality_parcels(): void
    {
        $this->createBoundary($this->first, 120.50, 15.40);
        $this->createBoundary($this->second, 120.60, 15.50);
        $firstFarmer = Farmer::create(['municipality_id' => $this->first->id, 'first_name' => 'Anao', 'last_name' => 'Farmer']);
        $secondFarmer = Farmer::create(['municipality_id' => $this->second->id, 'first_name' => 'Ramos', 'last_name' => 'Farmer']);
        $firstPlot = $this->plot($firstFarmer, 'Anao parcel', 120.505, 15.405);
        $this->plot($secondFarmer, 'Ramos parcel', 120.605, 15.505);

        $response = $this->actingAs($this->provincial)
            ->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->first->id]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'parcels')
            ->assertJsonPath('parcels.0.id', $firstPlot->id)
            ->assertJsonPath('parcels.0.geofence_status', 'inside')
            ->assertJsonPath('snapshot.source_size', 1280)
            ->assertJsonPath('snapshot.viewport_size', 640)
            ->assertJsonMissing(['name' => 'Ramos parcel']);
        $this->assertStringContainsString('?v=', $response->json('snapshot.base_map_url'));
    }

    public function test_snapshot_frame_expands_to_keep_an_outside_municipal_parcel_visible(): void
    {
        $this->createBoundary($this->first, 120.50, 15.40);
        $farmer = Farmer::create(['municipality_id' => $this->first->id, 'first_name' => 'Frame', 'last_name' => 'Owner']);

        $firstUrl = $this->actingAs($this->municipal)
            ->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->first->id]))
            ->assertOk()
            ->json('snapshot.base_map_url');

        $this->plot($farmer, 'Distant municipal parcel', 121.10, 16.00);

        $secondUrl = $this->actingAs($this->municipal)
            ->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->first->id]))
            ->assertOk()
            ->json('snapshot.base_map_url');

        $this->assertNotSame($firstUrl, $secondUrl);
    }

    public function test_map_data_labels_inside_crossing_and_outside_municipal_parcels(): void
    {
        $this->createBoundary($this->first, 120.50, 15.40);
        $farmer = Farmer::create(['municipality_id' => $this->first->id, 'first_name' => 'Boundary', 'last_name' => 'Review']);
        $this->plot($farmer, 'Inside parcel', 120.505, 15.405);
        $this->plot($farmer, 'Crossing parcel', 120.519, 15.409);
        $this->plot($farmer, 'Outside parcel', 120.530, 15.430);

        $this->actingAs($this->municipal)
            ->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->first->id]))
            ->assertOk()
            ->assertJsonCount(3, 'parcels')
            ->assertJsonFragment(['name' => 'Inside parcel', 'geofence_status' => 'inside'])
            ->assertJsonFragment(['name' => 'Crossing parcel', 'geofence_status' => 'partial'])
            ->assertJsonFragment(['name' => 'Outside parcel', 'geofence_status' => 'outside'])
            ->assertJsonPath('stats.partial', 1)
            ->assertJsonPath('stats.outside', 1);
    }

    public function test_authorized_user_can_fetch_a_same_origin_satellite_snapshot_base(): void
    {
        config(['services.google_maps.static_key' => 'static-test-key']);
        Http::fake([
            'maps.googleapis.com/maps/api/staticmap*' => Http::response('PNG-CONTENT', 200, ['Content-Type' => 'image/png']),
        ]);
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);
        $farmer = Farmer::create(['municipality_id' => $this->first->id, 'first_name' => 'Map', 'last_name' => 'Owner']);
        $this->plot($farmer, 'Mapped parcel', 120.505, 15.405);

        $this->actingAs($this->municipal)
            ->get(route('municipality-boundaries.snapshot-base', $boundary))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertContent('PNG-CONTENT');

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'exported',
            'module' => 'Municipality geofences',
        ]);

        $this->actingAs($this->municipal)
            ->postJson(route('municipality-boundaries.snapshot-exported', $boundary))
            ->assertOk()
            ->assertJsonPath('recorded', true);

        $audit = DB::table('audit_logs')
            ->where('event', 'exported')
            ->where('module', 'Municipality geofences')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $metadata = json_decode($audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $metadata['parcel_statuses']['inside']);
        $this->assertArrayNotHasKey('geometry', $metadata);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://maps.googleapis.com/maps/api/staticmap')
            && $request['maptype'] === 'satellite'
            && $request['scale'] === 2
            && $request['key'] === 'static-test-key');
    }

    public function test_foreign_municipal_user_cannot_fetch_snapshot_base(): void
    {
        config(['services.google_maps.static_key' => 'static-test-key']);
        $boundary = $this->createBoundary($this->second, 120.60, 15.50);

        $this->actingAs($this->municipal)
            ->get(route('municipality-boundaries.snapshot-base', $boundary))
            ->assertForbidden();
    }

    public function test_snapshot_base_requires_an_active_boundary_and_static_maps_key(): void
    {
        $draft = $this->createBoundary($this->first, 120.50, 15.40);
        $draft->update(['status' => MunicipalityBoundary::STATUS_DRAFT]);
        config(['services.google_maps.static_key' => 'static-test-key']);

        $this->actingAs($this->municipal)
            ->get(route('municipality-boundaries.snapshot-base', $draft))
            ->assertUnprocessable();

        $active = $this->createBoundary($this->second, 120.60, 15.50);
        config(['services.google_maps.static_key' => '']);

        $this->actingAs($this->provincial)
            ->get(route('municipality-boundaries.snapshot-base', $active))
            ->assertStatus(503);
    }

    public function test_snapshot_base_reports_an_upstream_static_map_failure(): void
    {
        config(['services.google_maps.static_key' => 'static-test-key']);
        Http::fake([
            'maps.googleapis.com/maps/api/staticmap*' => Http::response('denied', 403, ['Content-Type' => 'text/plain']),
        ]);
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);

        $this->actingAs($this->municipal)
            ->get(route('municipality-boundaries.snapshot-base', $boundary))
            ->assertStatus(502)
            ->assertSee('Google denied the satellite image request.')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
    }

    public function test_snapshot_connection_failure_does_not_expose_the_request_url_or_key(): void
    {
        config(['services.google_maps.static_key' => 'static-test-key']);
        Log::spy();
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection failed for https://maps.googleapis.com/maps/api/staticmap?key=static-test-key');
        });
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);

        $this->actingAs($this->municipal)
            ->get(route('municipality-boundaries.snapshot-base', $boundary))
            ->assertStatus(502)
            ->assertSee('The server could not connect to Google Maps.')
            ->assertDontSee('static-test-key');

        Log::shouldHaveReceived('warning')->once()
            ->with('Municipality satellite snapshot connection failed.');
        Log::shouldNotHaveReceived('error');
    }

    public function test_snapshot_provider_failure_is_not_cached_and_can_recover(): void
    {
        config(['services.google_maps.static_key' => 'static-test-key']);
        Log::spy();
        Http::fake([
            'maps.googleapis.com/maps/api/staticmap*' => Http::sequence()
                ->push('Sensitive provider response static-test-key', 429)
                ->push('PNG-CONTENT', 200, ['Content-Type' => 'image/png']),
        ]);
        $boundary = $this->createBoundary($this->first, 120.50, 15.40);
        $url = route('municipality-boundaries.snapshot-base', $boundary);

        $this->actingAs($this->municipal)->get($url)
            ->assertStatus(502)
            ->assertSee('Google Maps usage limit was reached.')
            ->assertDontSee('static-test-key');
        $this->get($url)->assertOk()->assertContent('PNG-CONTENT');
        $this->get($url)->assertOk()->assertContent('PNG-CONTENT');

        Http::assertSentCount(2);
        Log::shouldHaveReceived('warning')->once()
            ->with('Municipality satellite snapshot provider failure.', ['upstream_status' => 429]);
    }

    private function createSchema(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('province')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->timestamp('evaluation_expires_at')->nullable();
            $table->boolean('evaluation_password_pending')->default(false);
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('ffrs')->nullable();
            $table->string('farm_location')->nullable();
            $table->string('public_map_token', 40)->nullable();
            $table->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('farmer_id');
            $table->string('name')->nullable();
            $table->json('polygon_json');
            $table->decimal('area_ha', 15, 4)->nullable();
            $table->decimal('centroid_lat', 10, 7)->nullable();
            $table->decimal('centroid_lng', 11, 7)->nullable();
            $table->string('color', 16)->nullable();
            $table->timestamps();
        });
        Schema::create('municipality_boundaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('name');
            $table->json('geojson');
            $table->string('color', 7);
            $table->decimal('fill_opacity', 3, 2)->default(.2);
            $table->string('status', 20);
            $table->decimal('area_ha', 15, 4);
            $table->decimal('centroid_lat', 10, 7);
            $table->decimal('centroid_lng', 11, 7);
            $table->decimal('min_lat', 10, 7);
            $table->decimal('max_lat', 10, 7);
            $table->decimal('min_lng', 11, 7);
            $table->decimal('max_lng', 11, 7);
            $table->unsignedInteger('vertex_count');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('actor_role', 40)->nullable();
            $table->string('event', 40);
            $table->string('module', 80);
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id', 64)->nullable();
            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->text('request_url')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function user(string $role, ?int $municipalityId, string $email): User
    {
        return User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('password'), 'role' => $role, 'province_id' => in_array($role, User::PROVINCIAL_ROLES, true) ? Province::where('name', 'Tarlac')->value('id') : null, 'municipality_id' => $municipalityId, 'is_active' => true]);
    }

    private function createBoundary(Municipality $municipality, float $lng, float $lat, float $size = 0.02): MunicipalityBoundary
    {
        $response = $this->actingAs($this->superAdmin)->postJson(route('municipality-boundaries.store'), $this->boundaryPayload($municipality->id, $lng, $lat, $size));
        $response->assertCreated();

        return MunicipalityBoundary::findOrFail($response->json('boundary.id'));
    }

    /** @return array<string, mixed> */
    private function boundaryPayload(int $municipalityId, float $lng, float $lat, float $size = 0.02): array
    {
        return ['municipality_id' => $municipalityId, 'name' => 'Official Boundary', 'color' => '#15803D', 'status' => 'active', 'geojson' => $this->geoJsonSquare($lng, $lat, $size)];
    }

    /** @return array<string, mixed> */
    private function geoJsonSquare(float $lng, float $lat, float $size): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$lng, $lat], [$lng + $size, $lat], [$lng + $size, $lat + $size], [$lng, $lat + $size], [$lng, $lat]]]];
    }

    /** @return array<int, array{lat:float,lng:float}> */
    private function latLngRing(float $lng, float $lat, float $size = 0.002): array
    {
        return [['lat' => $lat, 'lng' => $lng], ['lat' => $lat, 'lng' => $lng + $size], ['lat' => $lat + $size, 'lng' => $lng + $size], ['lat' => $lat + $size, 'lng' => $lng]];
    }

    private function plot(Farmer $farmer, string $name, float $lng, float $lat): FarmPlot
    {
        return FarmPlot::create(['farmer_id' => $farmer->id, 'name' => $name, 'polygon_json' => $this->latLngRing($lng, $lat), 'area_ha' => 4.5, 'centroid_lat' => $lat + .001, 'centroid_lng' => $lng + .001, 'color' => '#22C55E']);
    }
}
