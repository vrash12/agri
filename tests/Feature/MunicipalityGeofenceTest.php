<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

        $this->first = Municipality::create(['name' => 'Anao', 'province' => 'Tarlac', 'code' => 'ANA', 'is_active' => true]);
        $this->second = Municipality::create(['name' => 'Ramos', 'province' => 'Tarlac', 'code' => 'RAM', 'is_active' => true]);
        $this->superAdmin = $this->user(User::ROLE_SUPER_ADMIN, null, 'super@example.test');
        $this->provincial = $this->user(User::ROLE_PROVINCIAL_STAFF, null, 'province@example.test');
        $this->municipal = $this->user(User::ROLE_MUNICIPAL_STAFF, $this->first->id, 'anao@example.test');
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
            ->assertStatus(502);
    }

    private function createSchema(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('province')->nullable();
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
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
        return User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('password'), 'role' => $role, 'municipality_id' => $municipalityId, 'is_active' => true]);
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
