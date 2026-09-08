<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\User;
use App\Support\GeoGeometry;
use Database\Seeders\BaguioCityBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

class BaguioCityBoundarySeederTest extends TestCase
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

    public function test_import_creates_one_idempotent_boundary_with_attributed_audit_and_invalidates_cache(): void
    {
        app(BaguioCityBoundarySeeder::class)->run();
        $municipality = Municipality::query()->sole();
        $boundary = MunicipalityBoundary::query()->sole();
        $version = $boundary->updated_at->toISOString();

        $this->assertSame('Baguio City', $municipality->name);
        $this->assertSame('BAGUIO', $municipality->code);
        $this->assertSame('Benguet', $municipality->province);
        $this->assertSame(MunicipalityBoundary::STATUS_ACTIVE, $boundary->status);
        $this->assertEqualsWithDelta(5861.586, $boundary->area_ha, 0.01);
        $this->assertSame(33, $boundary->vertex_count);
        $this->assertSame($this->actor->id, $boundary->created_by);
        $audit = AuditLog::query()->where('module', 'Municipality geofences')->sole();
        $this->assertSame($this->actor->id, $audit->user_id);
        $this->assertSame($municipality->id, $audit->municipality_id);
        $this->assertSame('imported', $audit->event);
        $this->assertTrue($audit->metadata['workspace_created']);
        $this->assertSame('planning_reference', $audit->metadata['data_classification']);
        $this->assertSame('9469f09', $audit->metadata['source_revision']);
        $this->assertSame('1430300000', $audit->metadata['psgc_code']);
        $this->assertSame('CC BY 3.0 IGO', $audit->metadata['source_license']);

        Cache::put('municipality-boundary:active:v1:'.$municipality->id, 'stale');
        app(BaguioCityBoundarySeeder::class)->run();

        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame(1, MunicipalityBoundary::query()->count());
        $this->assertSame(1, AuditLog::query()->count());
        $this->assertSame($version, $boundary->fresh()->updated_at->toISOString());
        $this->assertFalse(Cache::has('municipality-boundary:active:v1:'.$municipality->id));
    }

    /** @dataProvider existingWorkspaceIdentities */
    public function test_import_reuses_existing_city_identity_without_changing_its_name_or_code(string $name, string $code): void
    {
        $municipality = $this->municipality($name, $code);
        app(BaguioCityBoundarySeeder::class)->run();

        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame($municipality->id, MunicipalityBoundary::query()->sole()->municipality_id);
        $this->assertSame($name, $municipality->fresh()->name);
        $this->assertSame($code, $municipality->fresh()->code);
        $this->assertFalse(AuditLog::query()->where('event', 'imported')->sole()->metadata['workspace_created']);
    }

    public static function existingWorkspaceIdentities(): array
    {
        return [
            ['Baguio', 'BAG'], ['City of Baguio', 'CUSTOM'], [' baguio city ', 'CITY'],
            ['City workspace', 'BAGUIO'], ['City workspace', '1430300000'], ['City workspace', '141102000'],
        ];
    }

    public function test_ambiguous_city_workspaces_are_rejected_without_writes(): void
    {
        $this->municipality('Baguio', 'BAG');
        $this->municipality('City of Baguio', 'BAGUIO');
        $this->assertImportRejected('Multiple Baguio workspaces');
        $this->assertSame(2, Municipality::query()->count());
        $this->assertSame(0, MunicipalityBoundary::query()->count());
    }

    public function test_inactive_city_workspace_is_not_reactivated(): void
    {
        $municipality = $this->municipality('Baguio', 'BAG', false);
        $this->assertImportRejected('workspace is inactive');
        $this->assertFalse($municipality->fresh()->is_active);
        $this->assertSame(0, MunicipalityBoundary::query()->count());
    }

    public function test_missing_active_super_admin_prevents_workspace_creation(): void
    {
        $this->actor->forceFill(['is_active' => false])->saveQuietly();
        $this->assertImportRejected('account is required');
        $this->assertSame(0, Municipality::query()->count());
        $this->assertSame(0, MunicipalityBoundary::query()->count());
    }

    public function test_foreign_active_overlap_rolls_back_city_workspace_creation(): void
    {
        $foreign = $this->municipality('Another workspace', 'OTHER');
        $boundary = $this->boundary($foreign, 'Existing active boundary');
        $this->assertImportRejected('conflicts with active boundary');
        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame(1, MunicipalityBoundary::query()->count());
        $this->assertSame(MunicipalityBoundary::STATUS_ACTIVE, $boundary->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('event', 'imported')->count());
    }

    public function test_existing_active_city_boundary_is_preserved(): void
    {
        $municipality = $this->municipality('Baguio', 'BAG');
        $boundary = $this->boundary($municipality, 'LGU approved boundary');
        $this->assertImportRejected('different active boundary');
        $this->assertSame(1, MunicipalityBoundary::query()->count());
        $this->assertSame('LGU approved boundary', $boundary->fresh()->name);
        $this->assertSame(MunicipalityBoundary::STATUS_ACTIVE, $boundary->fresh()->status);
    }

    public function test_rerun_does_not_reactivate_an_archived_reference(): void
    {
        app(BaguioCityBoundarySeeder::class)->run();
        $boundary = MunicipalityBoundary::query()->sole();
        $boundary->forceFill(['status' => MunicipalityBoundary::STATUS_ARCHIVED, 'archived_at' => now()])->save();
        $this->assertImportRejected('changed or deactivated');
        $this->assertSame(MunicipalityBoundary::STATUS_ARCHIVED, $boundary->fresh()->status);
    }

    private function assertImportRejected(string $message): void
    {
        try {
            app(BaguioCityBoundarySeeder::class)->run();
            $this->fail('The unsafe import should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function municipality(string $name, string $code, bool $active = true): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => $name, 'code' => $code, 'province' => 'Benguet', 'province_id' => $this->referenceProvinceId('Benguet'), 'is_active' => $active,
        ]));
    }

    private function boundary(Municipality $municipality, string $name): MunicipalityBoundary
    {
        $document = json_decode(file_get_contents(database_path('seeders/data/baguio_reference_boundary.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $geometry = app(GeoGeometry::class)->prepare($document['features'][0]['geometry']);
        $metadata = app(GeoGeometry::class)->metadata($geometry);

        return MunicipalityBoundary::query()->create([
            'municipality_id' => $municipality->id, 'name' => $name, 'geojson' => $geometry,
            'status' => MunicipalityBoundary::STATUS_ACTIVE, 'area_ha' => $metadata['area_ha'],
            'centroid_lat' => $metadata['centroid_lat'], 'centroid_lng' => $metadata['centroid_lng'],
            'min_lat' => $metadata['min_lat'], 'max_lat' => $metadata['max_lat'],
            'min_lng' => $metadata['min_lng'], 'max_lng' => $metadata['max_lng'],
            'vertex_count' => $metadata['vertices'], 'created_by' => $this->actor->id,
        ]);
    }
}
