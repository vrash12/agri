<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\User;
use App\Support\GeoGeometry;
use Database\Seeders\BulacanMunicipalityBoundarySeeder;
use Database\Seeders\BulacanProvinceBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

class BulacanMunicipalityBoundarySeederTest extends TestCase
{
    use ReferenceProvinceSchema;

    private const PROVINCE_BOUNDARY = 'Bulacan Province Planning Reference · geoBoundaries 2020';

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

    public function test_all_twenty_four_imports_are_attributed_idempotent_and_clear_their_boundary_cache(): void
    {
        app(BulacanMunicipalityBoundarySeeder::class)->run();

        $expected = [
            'Angat' => '0301401000', 'Balagtas' => '0301402000', 'Baliuag' => '0301403000',
            'Bocaue' => '0301404000', 'Bulakan' => '0301405000', 'Bustos' => '0301406000',
            'Calumpit' => '0301407000', 'Guiguinto' => '0301408000', 'Hagonoy' => '0301409000',
            'Malolos City' => '0301410000', 'Marilao' => '0301411000', 'Meycauayan City' => '0301412000',
            'Norzagaray' => '0301413000', 'Obando' => '0301414000', 'Pandi' => '0301415000',
            'Paombong' => '0301416000', 'Plaridel' => '0301417000', 'Pulilan' => '0301418000',
            'San Ildefonso' => '0301419000', 'San Jose del Monte City' => '0301420000',
            'San Miguel' => '0301421000', 'San Rafael' => '0301422000', 'Santa Maria' => '0301423000',
            'Doña Remedios Trinidad' => '0301424000',
        ];
        $this->assertCount(24, $expected);
        $this->assertSame(24, Municipality::query()->count());
        $this->assertSame(24, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(24, AuditLog::query()->count());

        foreach ($expected as $name => $psgc) {
            $municipality = Municipality::query()->where('name', $name)->sole();
            $boundary = MunicipalityBoundary::query()->where('municipality_id', $municipality->id)->sole();
            $audit = AuditLog::query()->where('municipality_id', $municipality->id)->sole();
            $this->assertSame('Bulacan', $municipality->province);
            $this->assertSame($this->referenceProvinceId('Bulacan'), (int) $municipality->province_id);
            $this->assertTrue($municipality->is_active);
            $this->assertSame($name.' Planning Reference · geoBoundaries 2020', $boundary->name);
            $this->assertSame(MunicipalityBoundary::STATUS_ACTIVE, $boundary->status);
            $this->assertGreaterThan(1500, $boundary->area_ha);
            $this->assertSame($this->actor->id, $boundary->created_by);
            $this->assertSame($this->actor->id, $boundary->updated_by);
            $this->assertSame($this->actor->id, $audit->user_id);
            $this->assertSame('Municipality geofences', $audit->module);
            $this->assertSame('imported', $audit->event);
            $this->assertTrue($audit->metadata['workspace_created']);
            $this->assertSame('planning_reference', $audit->metadata['data_classification']);
            $this->assertSame('ADM3 municipality', $audit->metadata['administrative_level']);
            $this->assertSame('9469f09', $audit->metadata['source_revision']);
            $this->assertSame($psgc, $audit->metadata['psgc_code']);
            $this->assertSame('CC BY 3.0 IGO', $audit->metadata['source_license']);
            $this->assertSame('d58ea603bba4cc74c86e9949df9f0dad3fba3c4b54695de326a3af4e62682a16', $audit->metadata['source_checksum']);
            Cache::put('municipality-boundary:active:v1:'.$municipality->id, 'stale');
        }

        MunicipalityBoundary::query()->first()->forceFill(['color' => '#A855F7'])->saveQuietly();
        $versions = MunicipalityBoundary::query()->orderBy('id')->get()->map->getAttributes()->all();
        $this->travel(1)->minutes();

        app(BulacanMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(24, Municipality::query()->count());
        $this->assertSame(24, AuditLog::query()->count());
        $this->assertSame($versions, MunicipalityBoundary::query()->orderBy('id')->get()->map->getAttributes()->all());
        foreach (Municipality::query()->pluck('id') as $id) {
            $this->assertFalse(Cache::has('municipality-boundary:active:v1:'.$id));
        }
        $this->assertSame(1, User::query()->count());
        $this->assertFalse(Schema::hasTable('farmers'));
        $this->assertFalse(Schema::hasTable('rice_seed_distributions'));
    }

    public function test_the_province_reference_is_archived_once_and_its_workspace_is_left_intact(): void
    {
        app(BulacanProvinceBoundarySeeder::class)->run();
        $province = Municipality::query()->where('code', 'BULACAN')->sole();
        $workspace = $province->getAttributes();
        $provinceBoundary = MunicipalityBoundary::query()->sole();
        Cache::put('municipality-boundary:active:v1:'.$province->id, 'stale');

        app(BulacanMunicipalityBoundarySeeder::class)->run();

        // The province workspace itself is never renamed, reassigned, or reused for a municipality.
        $this->assertSame($workspace, $province->fresh()->getAttributes());
        $this->assertSame(25, Municipality::query()->count());
        $this->assertSame(24, MunicipalityBoundary::query()->active()->count());
        $this->assertFalse(Cache::has('municipality-boundary:active:v1:'.$province->id));

        $archived = $provinceBoundary->fresh();
        $this->assertSame(MunicipalityBoundary::STATUS_ARCHIVED, $archived->status);
        $this->assertNotNull($archived->archived_at);
        $this->assertSame($this->actor->id, $archived->updated_by);

        $event = AuditLog::query()->where('event', 'archived')->sole();
        $this->assertSame('Municipality geofences', $event->module);
        $this->assertSame($province->id, $event->municipality_id);
        $this->assertSame($this->actor->id, $event->user_id);
        $this->assertSame('superseded_by_municipality_references', $event->metadata['reason']);
        $this->assertSame(self::PROVINCE_BOUNDARY, $event->metadata['superseded_boundary']);

        // Bulakan is its own municipality workspace, not the province workspace reused.
        $bulakan = Municipality::query()->where('code', 'BULAKAN')->sole();
        $this->assertSame('Bulakan', $bulakan->name);
        $this->assertNotSame($province->id, $bulakan->id);

        $auditCount = AuditLog::query()->count();
        app(BulacanMunicipalityBoundarySeeder::class)->run();
        $this->assertSame($auditCount, AuditLog::query()->count());
        $this->assertSame(1, MunicipalityBoundary::query()->where('status', MunicipalityBoundary::STATUS_ARCHIVED)->count());
    }

    public function test_an_identically_named_reference_in_another_province_is_not_archived(): void
    {
        $foreignProvince = Province::query()->where('name', 'Tarlac')->sole();
        $foreign = Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => 'Tarlac evaluation workspace', 'code' => 'TARLAC-EVAL', 'province' => 'Tarlac',
            'province_id' => $foreignProvince->id, 'is_active' => true,
        ])->refresh());
        $boundary = $this->boundary($foreign, self::PROVINCE_BOUNDARY, 'San Miguel');
        $original = $boundary->getAttributes();

        // The foreign copy shares the name but not the province, so it must survive untouched.
        $this->assertImportRejected('conflicts with active boundary');
        $this->assertSame($original, $boundary->fresh()->getAttributes());
        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_a_late_conflict_rolls_back_the_archive_and_every_new_record(): void
    {
        app(BulacanProvinceBoundarySeeder::class)->run();
        $provinceBoundary = MunicipalityBoundary::query()->sole();
        $provinceState = $provinceBoundary->getAttributes();
        $auditState = AuditLog::query()->orderBy('id')->get()->mapWithKeys(
            fn (AuditLog $audit): array => [$audit->id => $audit->getAttributes()]
        );

        $foreign = Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => 'Neighbouring office', 'code' => 'OTHER', 'province' => 'Bulacan',
            'province_id' => $this->referenceProvinceId('Bulacan'), 'is_active' => true,
        ])->refresh());
        $blocking = $this->boundary($foreign, 'Existing approved area', 'Santa Maria');
        $blockingState = $blocking->getAttributes();

        $this->assertImportRejected('conflicts with active boundary');

        $this->assertSame($provinceState, $provinceBoundary->fresh()->getAttributes());
        $this->assertTrue($provinceBoundary->fresh()->isActive());
        $this->assertSame($blockingState, $blocking->fresh()->getAttributes());
        $this->assertSame(2, Municipality::query()->count());
        $this->assertSame(2, MunicipalityBoundary::query()->count());
        foreach ($auditState as $id => $attributes) {
            $this->assertSame($attributes, AuditLog::query()->findOrFail($id)->getAttributes());
        }
        $this->assertSame($auditState->count(), AuditLog::query()->count());
    }

    public function test_existing_name_and_psgc_identities_are_reused_without_renaming(): void
    {
        $workspaces = [
            $this->municipality(' Municipality of Pandi ', 'LOCAL-PANDI'),
            $this->municipality('Malolos City', 'LOCAL-MALOLOS'),
            $this->municipality('Central office', '031421000'),
        ];

        app(BulacanMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(24, Municipality::query()->count());
        foreach ($workspaces as $workspace) {
            $this->assertSame($workspace->getAttributes(), $workspace->fresh()->getAttributes());
            $this->assertSame(1, MunicipalityBoundary::query()->where('municipality_id', $workspace->id)->count());
            $this->assertFalse(AuditLog::query()->where('municipality_id', $workspace->id)->sole()->metadata['workspace_created']);
        }
    }

    public function test_an_ambiguous_inactive_or_foreign_workspace_prevents_the_entire_import(): void
    {
        $duplicate = $this->municipality('Calumpit', 'LOCAL-CALUMPIT');
        $extra = $this->municipality('Another office', '0301407000');
        $this->assertImportRejected('Multiple Calumpit workspaces');
        $this->assertNoImportRecords();

        $extra->forceFill(['code' => 'UNRELATED'])->saveQuietly();
        $duplicate->forceFill(['is_active' => false])->saveQuietly();
        $this->assertImportRejected('workspace is inactive');
        $this->assertNoImportRecords();

        $duplicate->forceFill(['is_active' => true, 'province' => 'Pampanga'])->saveQuietly();
        $this->assertImportRejected('different province');
        $this->assertSame('Pampanga', $duplicate->fresh()->province);
        $this->assertNoImportRecords();
    }

    public function test_a_different_active_hagonoy_boundary_is_preserved(): void
    {
        $municipality = $this->municipality('Hagonoy', 'HAGONOY');
        $boundary = $this->boundary($municipality, 'Hagonoy Official Boundary', 'Hagonoy');
        $original = $boundary->getAttributes();

        $this->assertImportRejected('different active boundary');

        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame(1, MunicipalityBoundary::query()->count());
        $this->assertSame($original, $boundary->fresh()->getAttributes());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_an_active_administrator_is_required_before_any_import_writes(): void
    {
        $this->actor->forceFill(['is_active' => false])->saveQuietly();

        $this->assertImportRejected('account is required');

        $this->assertSame(0, Municipality::query()->count());
        $this->assertNoImportRecords();
    }

    private function assertImportRejected(string $message): void
    {
        try {
            app(BulacanMunicipalityBoundarySeeder::class)->run();
            $this->fail('The unsafe import should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function assertNoImportRecords(): void
    {
        $this->assertSame(0, MunicipalityBoundary::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    private function municipality(string $name, string $code, bool $active = true): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => $name, 'code' => $code, 'province' => 'Bulacan',
            'province_id' => $this->referenceProvinceId('Bulacan'), 'is_active' => $active,
        ])->refresh());
    }

    private function boundary(Municipality $municipality, string $name, string $sourceMunicipality): MunicipalityBoundary
    {
        $document = json_decode(
            file_get_contents(database_path('seeders/data/bulacan_municipality_reference_boundaries.geojson')),
            true, 512, JSON_THROW_ON_ERROR
        );
        $feature = collect($document['features'])->firstWhere('properties.shapeName', $sourceMunicipality);
        $this->assertIsArray($feature);
        $geometry = app(GeoGeometry::class)->prepare($feature['geometry']);
        $metadata = app(GeoGeometry::class)->metadata($geometry);

        return MunicipalityBoundary::query()->create([
            'municipality_id' => $municipality->id, 'name' => $name, 'geojson' => $geometry,
            'color' => '#A855F7', 'status' => MunicipalityBoundary::STATUS_ACTIVE, 'area_ha' => $metadata['area_ha'],
            'centroid_lat' => $metadata['centroid_lat'], 'centroid_lng' => $metadata['centroid_lng'],
            'min_lat' => $metadata['min_lat'], 'max_lat' => $metadata['max_lat'],
            'min_lng' => $metadata['min_lng'], 'max_lng' => $metadata['max_lng'],
            'vertex_count' => $metadata['vertices'], 'created_by' => $this->actor->id,
        ])->refresh();
    }
}
