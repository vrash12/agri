<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\User;
use App\Support\GeoGeometry;
use Database\Seeders\BaguioCityBoundarySeeder;
use Database\Seeders\BenguetMunicipalityBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

class BenguetMunicipalityBoundarySeederTest extends TestCase
{
    use ReferenceProvinceSchema;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Cache::clear();

        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        (require database_path('migrations/2026_09_03_000100_create_municipality_boundaries_table.php'))->up();
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        $this->createProvinceSchema();
        $this->actor = User::withoutEvents(fn () => User::query()->create([
            'name' => 'Boundary Test Administrator', 'email' => 'boundary-admin@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true,
        ]));
    }

    public function test_all_three_imports_are_attributed_idempotent_and_invalidate_their_cached_boundaries(): void
    {
        app(BenguetMunicipalityBoundarySeeder::class)->run();
        $expected = [
            'Atok' => ['ATOK', '1401101000', 17910.3503],
            'La Trinidad' => ['LATRINIDAD', '1401110000', 7109.2414],
            'Tublay' => ['TUBLAY', '1401114000', 7671.2429],
        ];
        $this->assertSame(3, Municipality::query()->count());
        $this->assertSame(3, MunicipalityBoundary::query()->count());
        $this->assertSame(3, AuditLog::query()->count());

        foreach ($expected as $name => [$code, $psgc, $area]) {
            $municipality = Municipality::query()->where('name', $name)->sole();
            $boundary = MunicipalityBoundary::query()->where('municipality_id', $municipality->id)->sole();
            $audit = AuditLog::query()->where('municipality_id', $municipality->id)->sole();
            $this->assertSame($code, $municipality->code);
            $this->assertSame('Benguet', $municipality->province);
            $this->assertTrue($municipality->is_active);
            $this->assertSame($name.' Planning Reference · geoBoundaries 2020', $boundary->name);
            $this->assertSame(MunicipalityBoundary::STATUS_ACTIVE, $boundary->status);
            $this->assertEqualsWithDelta($area, $boundary->area_ha, 0.01);
            $this->assertSame($this->actor->id, $boundary->created_by);
            $this->assertSame($this->actor->id, $boundary->updated_by);
            $this->assertSame($this->actor->id, $audit->user_id);
            $this->assertSame('Municipality geofences', $audit->module);
            $this->assertSame('imported', $audit->event);
            $this->assertTrue($audit->metadata['workspace_created']);
            $this->assertSame('planning_reference', $audit->metadata['data_classification']);
            $this->assertSame('9469f09', $audit->metadata['source_revision']);
            $this->assertSame($psgc, $audit->metadata['psgc_code']);
            $this->assertSame('CC BY 3.0 IGO', $audit->metadata['source_license']);
            Cache::put('municipality-boundary:active:v1:'.$municipality->id, 'stale');
        }

        $versions = MunicipalityBoundary::query()->orderBy('id')->get()->map->getAttributes()->all();
        $this->travel(1)->minutes();
        app(BenguetMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(3, Municipality::query()->count());
        $this->assertSame(3, MunicipalityBoundary::query()->count());
        $this->assertSame(3, AuditLog::query()->count());
        $this->assertSame($versions, MunicipalityBoundary::query()->orderBy('id')->get()->map->getAttributes()->all());
        foreach (Municipality::query()->pluck('id') as $id) {
            $this->assertFalse(Cache::has('municipality-boundary:active:v1:'.$id));
        }
    }

    public function test_baguio_boundary_can_remain_active_and_unchanged_beside_the_three_imports(): void
    {
        app(BaguioCityBoundarySeeder::class)->run();
        $baguio = MunicipalityBoundary::query()->sole();
        $original = $baguio->getAttributes();

        app(BenguetMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(4, Municipality::query()->count());
        $this->assertSame(4, MunicipalityBoundary::query()->where('status', MunicipalityBoundary::STATUS_ACTIVE)->count());
        $this->assertSame($original, $baguio->fresh()->getAttributes());
        $this->assertSame(4, AuditLog::query()->where('event', 'imported')->count());
    }

    public function test_existing_name_and_psgc_identities_are_reused_without_renaming_workspaces(): void
    {
        $workspaces = [
            $this->municipality(' Municipality of La Trinidad ', 'LOCAL-LT'),
            $this->municipality('Northern office', '1401101000'),
            $this->municipality('Tublay', '141114000'),
        ];

        app(BenguetMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(3, Municipality::query()->count());
        foreach ($workspaces as $workspace) {
            $this->assertSame($workspace->getAttributes(), $workspace->fresh()->getAttributes());
            $this->assertSame(1, MunicipalityBoundary::query()->where('municipality_id', $workspace->id)->count());
            $audit = AuditLog::query()->where('municipality_id', $workspace->id)->sole();
            $this->assertFalse($audit->metadata['workspace_created']);
        }
    }

    public function test_late_tublay_overlap_rolls_back_every_new_workspace_boundary_and_audit(): void
    {
        $foreign = $this->municipality('Existing geofence owner', 'OTHER');
        $boundary = $this->boundary($foreign, 'Existing approved area', 'Tublay');
        $original = $boundary->getAttributes();
        Cache::put('municipality-boundary:active:v1:'.$foreign->id, 'preserved');

        $this->assertImportRejected('conflicts with active boundary');

        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame(1, MunicipalityBoundary::query()->count());
        $this->assertSame($original, $boundary->fresh()->getAttributes());
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame('preserved', Cache::get('municipality-boundary:active:v1:'.$foreign->id));
    }

    public function test_ambiguous_workspaces_prevent_the_entire_import(): void
    {
        $this->municipality('La Trinidad', 'LOCAL');
        $this->municipality('Another office', 'LA_TRINIDAD');

        $this->assertImportRejected('Multiple La Trinidad workspaces');

        $this->assertSame(2, Municipality::query()->count());
        $this->assertSame(0, MunicipalityBoundary::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_an_inactive_workspace_is_preserved_and_prevents_the_entire_import(): void
    {
        $municipality = $this->municipality('Tublay', 'TUBLAY', false);

        $this->assertImportRejected('workspace is inactive');

        $this->assertFalse($municipality->fresh()->is_active);
        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame(0, MunicipalityBoundary::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_a_matching_workspace_in_another_province_is_not_reassigned(): void
    {
        $municipality = $this->municipality('Atok', 'ATOK');
        $municipality->forceFill(['province' => 'Tarlac'])->saveQuietly();

        $this->assertImportRejected('different province');

        $this->assertSame('Tarlac', $municipality->fresh()->province);
        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame(0, MunicipalityBoundary::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_an_active_super_admin_is_required_before_any_import_writes(): void
    {
        $this->actor->forceFill(['is_active' => false])->saveQuietly();

        $this->assertImportRejected('account is required');

        $this->assertSame(0, Municipality::query()->count());
        $this->assertSame(0, MunicipalityBoundary::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_a_different_active_boundary_is_preserved_and_blocks_all_three_imports(): void
    {
        $municipality = $this->municipality('Tublay', 'TUBLAY');
        $boundary = $this->boundary($municipality, 'LGU approved boundary', 'Tublay');
        $original = $boundary->getAttributes();

        $this->assertImportRejected('different active boundary');

        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame(1, MunicipalityBoundary::query()->count());
        $this->assertSame($original, $boundary->fresh()->getAttributes());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_an_archived_reference_is_not_reactivated_on_rerun(): void
    {
        app(BenguetMunicipalityBoundarySeeder::class)->run();
        $boundary = MunicipalityBoundary::query()->where('name', 'Tublay Planning Reference · geoBoundaries 2020')->sole();
        $boundary->forceFill(['status' => MunicipalityBoundary::STATUS_ARCHIVED, 'archived_at' => now()])->saveQuietly();
        foreach (Municipality::query()->pluck('id') as $id) {
            Cache::put('municipality-boundary:active:v1:'.$id, 'preserved');
        }

        $this->assertImportRejected('changed or deactivated');

        $this->assertSame(MunicipalityBoundary::STATUS_ARCHIVED, $boundary->fresh()->status);
        $this->assertSame(3, MunicipalityBoundary::query()->count());
        $this->assertSame(3, AuditLog::query()->count());
        foreach (Municipality::query()->pluck('id') as $id) {
            $this->assertSame('preserved', Cache::get('municipality-boundary:active:v1:'.$id));
        }
    }

    public function test_a_changed_reference_is_not_overwritten_on_rerun(): void
    {
        app(BenguetMunicipalityBoundarySeeder::class)->run();
        $boundary = MunicipalityBoundary::query()->where('name', 'Tublay Planning Reference · geoBoundaries 2020')->sole();
        $geometry = $boundary->geojson;
        $geometry['coordinates'][0][1][0] += 0.00001;
        $boundary->forceFill(['geojson' => $geometry])->saveQuietly();

        $this->assertImportRejected('boundary');

        $this->assertSame($geometry, $boundary->fresh()->geojson);
        $this->assertSame(3, MunicipalityBoundary::query()->count());
        $this->assertSame(3, AuditLog::query()->count());
    }

    private function assertImportRejected(string $message): void
    {
        try {
            app(BenguetMunicipalityBoundarySeeder::class)->run();
            $this->fail('The unsafe import should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function municipality(string $name, string $code, bool $active = true): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => $name, 'code' => $code, 'province' => 'Benguet', 'province_id' => $this->referenceProvinceId('Benguet'), 'is_active' => $active,
        ])->refresh());
    }

    private function boundary(Municipality $municipality, string $name, string $sourceMunicipality): MunicipalityBoundary
    {
        $document = json_decode(file_get_contents(database_path('seeders/data/benguet_reference_boundaries.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $feature = collect($document['features'])->firstWhere('properties.shapeName', $sourceMunicipality);
        $geometry = app(GeoGeometry::class)->prepare($feature['geometry']);
        $metadata = app(GeoGeometry::class)->metadata($geometry);

        return MunicipalityBoundary::query()->create([
            'municipality_id' => $municipality->id, 'name' => $name, 'geojson' => $geometry,
            'status' => MunicipalityBoundary::STATUS_ACTIVE, 'area_ha' => $metadata['area_ha'],
            'centroid_lat' => $metadata['centroid_lat'], 'centroid_lng' => $metadata['centroid_lng'],
            'min_lat' => $metadata['min_lat'], 'max_lat' => $metadata['max_lat'],
            'min_lng' => $metadata['min_lng'], 'max_lng' => $metadata['max_lng'],
            'vertex_count' => $metadata['vertices'], 'created_by' => $this->actor->id,
        ])->refresh();
    }
}
