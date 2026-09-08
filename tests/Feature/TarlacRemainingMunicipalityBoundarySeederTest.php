<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\User;
use App\Support\GeoGeometry;
use Database\Seeders\TarlacRemainingMunicipalityBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

class TarlacRemainingMunicipalityBoundarySeederTest extends TestCase
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

    public function test_all_twelve_imports_are_attributed_idempotent_and_clear_their_boundary_cache(): void
    {
        app(TarlacRemainingMunicipalityBoundarySeeder::class)->run();
        $expected = [
            'Bamban' => '0306902000', 'Capas' => '0306904000', 'Gerona' => '0306906000',
            'La Paz' => '0306907000', 'Mayantoc' => '0306908000', 'Moncada' => '0306909000',
            'Pura' => '0306911000', 'San Clemente' => '0306913000', 'San Jose' => '0306918000',
            'San Manuel' => '0306914000', 'Santa Ignacia' => '0306915000', 'Victoria' => '0306917000',
        ];
        $this->assertSame(12, Municipality::query()->count());
        $this->assertSame(12, MunicipalityBoundary::query()->count());
        $this->assertSame(12, AuditLog::query()->count());

        foreach ($expected as $name => $psgc) {
            $municipality = Municipality::query()->where('name', $name)->sole();
            $boundary = MunicipalityBoundary::query()->where('municipality_id', $municipality->id)->sole();
            $audit = AuditLog::query()->where('municipality_id', $municipality->id)->sole();
            $this->assertSame('Tarlac', $municipality->province);
            $this->assertTrue($municipality->is_active);
            $this->assertSame($name.' Planning Reference · geoBoundaries 2020', $boundary->name);
            $this->assertSame(MunicipalityBoundary::STATUS_ACTIVE, $boundary->status);
            $this->assertGreaterThan(3000, $boundary->area_ha);
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
            $this->assertSame('3d9f24fabba985d50679d263998516cc05d24d2d6fc60390aa824d222d8d39b9', $audit->metadata['source_checksum']);
            Cache::put('municipality-boundary:active:v1:'.$municipality->id, 'stale');
        }

        MunicipalityBoundary::query()->first()->forceFill(['color' => '#A855F7'])->saveQuietly();
        $versions = MunicipalityBoundary::query()->orderBy('id')->get()->map->getAttributes()->all();
        $this->travel(1)->minutes();
        app(TarlacRemainingMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(12, Municipality::query()->count());
        $this->assertSame(12, AuditLog::query()->count());
        $this->assertSame($versions, MunicipalityBoundary::query()->orderBy('id')->get()->map->getAttributes()->all());
        foreach (Municipality::query()->pluck('id') as $id) {
            $this->assertFalse(Cache::has('municipality-boundary:active:v1:'.$id));
        }
        $this->assertSame(1, User::query()->count());
        $this->assertFalse(Schema::hasTable('farmers'));
        $this->assertFalse(Schema::hasTable('rice_seed_distributions'));
    }

    public function test_existing_six_reference_boundaries_and_archived_moncada_history_are_preserved(): void
    {
        $existing = [];
        foreach (['Anao', 'Camiling', 'Concepcion', 'Paniqui', 'Ramos', 'City of Tarlac'] as $name) {
            $municipality = $this->municipality($name === 'City of Tarlac' ? 'Tarlac City' : $name, strtoupper(str_replace(' ', '_', $name)));
            $existing[] = $this->boundary($municipality, $municipality->name.' Planning Reference · geoBoundaries 2020', $name);
        }
        $moncada = $this->municipality('Moncada', 'MONCADA');
        $legacy = $this->boundary($moncada, 'Moncada Official Boundary', 'Ramos');
        $legacy->forceFill(['status' => MunicipalityBoundary::STATUS_ARCHIVED, 'archived_at' => now(), 'color' => '#A855F7'])->saveQuietly();
        $existing[] = $legacy->refresh();
        $versions = collect($existing)->mapWithKeys(fn (MunicipalityBoundary $boundary): array => [$boundary->id => $boundary->getAttributes()]);
        foreach ($existing as $boundary) {
            Cache::put('municipality-boundary:active:v1:'.$boundary->municipality_id, 'preserved');
        }

        app(TarlacRemainingMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(18, Municipality::query()->count());
        $this->assertSame(19, MunicipalityBoundary::query()->count());
        $this->assertSame(18, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(12, AuditLog::query()->count());
        foreach ($existing as $boundary) {
            $this->assertSame($versions[$boundary->id], $boundary->fresh()->getAttributes());
            if ($boundary->municipality_id !== $moncada->id) {
                $this->assertSame('preserved', Cache::get('municipality-boundary:active:v1:'.$boundary->municipality_id));
            }
        }
        $this->assertFalse(Cache::has('municipality-boundary:active:v1:'.$moncada->id));
    }

    public function test_existing_name_and_psgc_identities_are_reused_without_renaming(): void
    {
        $workspaces = [
            $this->municipality(' Municipality of La Paz ', 'LOCAL-LP'),
            $this->municipality('Northern office', '0306914000'),
            $this->municipality('San Jose', '036918000'),
        ];

        app(TarlacRemainingMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(12, Municipality::query()->count());
        foreach ($workspaces as $workspace) {
            $this->assertSame($workspace->getAttributes(), $workspace->fresh()->getAttributes());
            $this->assertSame(1, MunicipalityBoundary::query()->where('municipality_id', $workspace->id)->count());
            $this->assertFalse(AuditLog::query()->where('municipality_id', $workspace->id)->sole()->metadata['workspace_created']);
        }
    }

    public function test_late_victoria_overlap_rolls_back_every_new_workspace_boundary_and_audit(): void
    {
        $foreign = $this->municipality('Existing geofence owner', 'OTHER');
        $boundary = $this->boundary($foreign, 'Existing approved area', 'Victoria');
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
        $this->municipality('San Jose', 'LOCAL');
        $this->municipality('Another office', '0306918000');

        $this->assertImportRejected('Multiple San Jose workspaces');

        $this->assertSame(2, Municipality::query()->count());
        $this->assertNoImportRecords();
    }

    public function test_an_inactive_workspace_is_preserved_and_prevents_the_entire_import(): void
    {
        $municipality = $this->municipality('Victoria', 'VICTORIA', false);

        $this->assertImportRejected('workspace is inactive');

        $this->assertFalse($municipality->fresh()->is_active);
        $this->assertSame(1, Municipality::query()->count());
        $this->assertNoImportRecords();
    }

    public function test_a_matching_name_in_another_province_is_not_reassigned(): void
    {
        $municipality = $this->municipality('San Manuel', 'OTHER-SAN-MANUEL');
        $municipality->forceFill(['province' => 'Pangasinan'])->saveQuietly();

        $this->assertImportRejected('different province');

        $this->assertSame('Pangasinan', $municipality->fresh()->province);
        $this->assertSame(1, Municipality::query()->count());
        $this->assertNoImportRecords();
    }

    public function test_an_active_super_admin_is_required_before_any_import_writes(): void
    {
        $this->actor->forceFill(['is_active' => false])->saveQuietly();

        $this->assertImportRejected('account is required');

        $this->assertSame(0, Municipality::query()->count());
        $this->assertNoImportRecords();
    }

    public function test_a_different_active_moncada_boundary_is_preserved(): void
    {
        $municipality = $this->municipality('Moncada', 'MONCADA');
        $boundary = $this->boundary($municipality, 'Moncada Official Boundary', 'Moncada');
        $original = $boundary->getAttributes();

        $this->assertImportRejected('different active boundary');

        $this->assertSame(1, Municipality::query()->count());
        $this->assertSame(1, MunicipalityBoundary::query()->count());
        $this->assertSame($original, $boundary->fresh()->getAttributes());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_an_archived_reference_is_not_reactivated_on_rerun_and_caches_remain_intact(): void
    {
        app(TarlacRemainingMunicipalityBoundarySeeder::class)->run();
        $boundary = MunicipalityBoundary::query()->where('name', 'Victoria Planning Reference · geoBoundaries 2020')->sole();
        $boundary->forceFill(['status' => MunicipalityBoundary::STATUS_ARCHIVED, 'archived_at' => now()])->saveQuietly();
        foreach (Municipality::query()->pluck('id') as $id) {
            Cache::put('municipality-boundary:active:v1:'.$id, 'preserved');
        }

        $this->assertImportRejected('changed or deactivated');

        $this->assertSame(MunicipalityBoundary::STATUS_ARCHIVED, $boundary->fresh()->status);
        $this->assertSame(12, MunicipalityBoundary::query()->count());
        $this->assertSame(12, AuditLog::query()->count());
        foreach (Municipality::query()->pluck('id') as $id) {
            $this->assertSame('preserved', Cache::get('municipality-boundary:active:v1:'.$id));
        }
    }

    public function test_a_changed_reference_is_not_overwritten_on_rerun(): void
    {
        app(TarlacRemainingMunicipalityBoundarySeeder::class)->run();
        $boundary = MunicipalityBoundary::query()->where('name', 'Victoria Planning Reference · geoBoundaries 2020')->sole();
        $geometry = $boundary->geojson;
        $geometry['coordinates'][0][1][0] += 0.00001;
        $boundary->forceFill(['geojson' => $geometry])->saveQuietly();

        $this->assertImportRejected('boundary');

        $this->assertSame($geometry, $boundary->fresh()->geojson);
        $this->assertSame(12, MunicipalityBoundary::query()->count());
        $this->assertSame(12, AuditLog::query()->count());
    }

    private function assertImportRejected(string $message): void
    {
        try {
            app(TarlacRemainingMunicipalityBoundarySeeder::class)->run();
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
            'name' => $name, 'code' => $code, 'province' => 'Tarlac', 'province_id' => $this->referenceProvinceId('Tarlac'), 'is_active' => $active,
        ])->refresh());
    }

    private function boundary(Municipality $municipality, string $name, string $sourceMunicipality): MunicipalityBoundary
    {
        $feature = null;
        foreach (['tarlac_reference_boundaries.geojson', 'tarlac_extended_reference_boundaries.geojson', 'tarlac_remaining_reference_boundaries.geojson'] as $filename) {
            $document = json_decode(file_get_contents(database_path('seeders/data/'.$filename)), true, 512, JSON_THROW_ON_ERROR);
            $feature = collect($document['features'])->firstWhere('properties.shapeName', $sourceMunicipality);
            if ($feature !== null) {
                break;
            }
        }
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
