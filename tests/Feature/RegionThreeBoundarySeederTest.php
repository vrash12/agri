<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\User;
use App\Support\GeoGeometry;
use App\Support\MunicipalityAccess;
use App\Support\ReferenceMunicipalityBoundaryImporter;
use Database\Seeders\AngelesCityBoundarySeeder;
use Database\Seeders\AuroraMunicipalityBoundarySeeder;
use Database\Seeders\BataanMunicipalityBoundarySeeder;
use Database\Seeders\BulacanMunicipalityBoundarySeeder;
use Database\Seeders\NuevaEcijaMunicipalityBoundarySeeder;
use Database\Seeders\OlongapoCityBoundarySeeder;
use Database\Seeders\PampangaMunicipalityBoundarySeeder;
use Database\Seeders\ZambalesMunicipalityBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

class RegionThreeBoundarySeederTest extends TestCase
{
    use ReferenceProvinceSchema;

    private const SEEDERS = [
        'Aurora' => AuroraMunicipalityBoundarySeeder::class,
        'Bataan' => BataanMunicipalityBoundarySeeder::class,
        'Nueva Ecija' => NuevaEcijaMunicipalityBoundarySeeder::class,
        'Pampanga' => PampangaMunicipalityBoundarySeeder::class,
        'Zambales' => ZambalesMunicipalityBoundarySeeder::class,
        'Angeles City' => AngelesCityBoundarySeeder::class,
        'Olongapo City' => OlongapoCityBoundarySeeder::class,
    ];

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
            $table->string('name')->unique();
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
            'name' => 'Region Three Boundary Administrator', 'email' => 'region-three-admin@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true,
        ]));
    }

    public function test_all_88_boundaries_have_the_correct_supervision_and_attribution(): void
    {
        $this->seedRegion();

        foreach (['Aurora' => 8, 'Bataan' => 12, 'Nueva Ecija' => 32, 'Pampanga' => 21, 'Zambales' => 13, 'Angeles City' => 1, 'Olongapo City' => 1] as $name => $count) {
            $province = Province::query()->where('name', $name)->sole();
            $this->assertTrue($province->is_active);
            $municipalities = Municipality::query()->where('province', $name)->get();
            $this->assertCount($count, $municipalities, "Incorrect {$name} municipality count.");
            $this->assertSame($count, MunicipalityBoundary::query()->active()
                ->whereIn('municipality_id', $municipalities->modelKeys())->count());

            foreach ($municipalities as $municipality) {
                $boundary = MunicipalityBoundary::query()->where('municipality_id', $municipality->id)->sole();
                $audit = AuditLog::query()->where('municipality_id', $municipality->id)->sole();
                $this->assertSame((int) $province->id, (int) $municipality->province_id);
                $this->assertTrue($municipality->is_active);
                $this->assertSame($municipality->name.' Planning Reference · geoBoundaries 2020', $boundary->name);
                $this->assertSame($this->actor->id, $boundary->created_by);
                $this->assertSame($this->actor->id, $audit->user_id);
                $this->assertSame((int) $province->id, $audit->province_id);
                $this->assertSame('imported', $audit->event);
                $this->assertSame('Municipality geofences', $audit->module);
                $this->assertTrue($audit->metadata['workspace_created']);
                $this->assertSame('planning_reference', $audit->metadata['data_classification']);
                $this->assertSame('9469f09', $audit->metadata['source_revision']);
                $this->assertSame('CC BY 3.0 IGO', $audit->metadata['source_license']);
                $this->assertMatchesRegularExpression('/^03[0-9]{8}$/', $audit->metadata['psgc_code']);
                $this->assertMatchesRegularExpression('/^03[0-9]{7}$/', $audit->metadata['legacy_psgc_code']);
            }
        }

        $this->assertSame(88, Municipality::query()->count());
        $this->assertSame(88, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(88, AuditLog::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertFalse(Schema::hasTable('farmers'));
        $this->assertFalse(Schema::hasTable('rice_seed_distributions'));
    }

    public function test_repeating_the_import_preserves_ids_geometry_styles_and_audits(): void
    {
        $this->seedRegion();
        MunicipalityBoundary::query()->firstOrFail()->forceFill(['color' => '#A855F7'])->saveQuietly();
        $before = $this->databaseState();

        foreach (Municipality::query()->pluck('id') as $id) {
            Cache::put('municipality-boundary:active:v1:'.$id, 'stale');
        }
        $this->travel(1)->minutes();
        $this->seedRegion();

        $this->assertSame($before, $this->databaseState());
        foreach (Municipality::query()->pluck('id') as $id) {
            $this->assertFalse(Cache::has('municipality-boundary:active:v1:'.$id));
        }
    }

    public function test_qualified_names_preserve_existing_workspaces_in_other_provinces(): void
    {
        Province::query()->create(['name' => 'Negros Oriental', 'is_active' => true]);
        $existing = [
            $this->municipality('Tarlac', 'San Jose', 'SAN_JOSE'),
            $this->municipality('Bulacan', 'San Miguel', 'SAN_MIGUEL'),
            $this->municipality('Negros Oriental', 'San Isidro', 'SAN_ISIDRO'),
        ];
        $before = array_map(fn (Municipality $municipality): array => $municipality->getAttributes(), $existing);

        $this->seedRegion();

        foreach ($existing as $index => $municipality) {
            $this->assertSame($before[$index], $municipality->fresh()->getAttributes());
            $this->assertSame(0, MunicipalityBoundary::query()->where('municipality_id', $municipality->id)->count());
        }
        foreach ([
            'SAN_JOSE_CITY_NUEVA_ECIJA' => 'San Jose City (Nueva Ecija)',
            'SAN_ISIDRO_NUEVA_ECIJA' => 'San Isidro (Nueva Ecija)',
        ] as $code => $name) {
            $this->assertSame($name, Municipality::query()->where('code', $code)->sole()->name);
        }
        $this->assertSame(91, Municipality::query()->count());
        $this->assertSame(88, MunicipalityBoundary::query()->count());
    }

    public function test_a_late_psgc_match_in_another_province_rolls_back_the_whole_province(): void
    {
        [, $identity] = $this->lastIdentity(NuevaEcijaMunicipalityBoundarySeeder::class);
        $this->municipality('Tarlac', 'Existing unrelated office', $identity['psgc_code']);
        $before = $this->databaseState();

        $this->assertImportRejected(NuevaEcijaMunicipalityBoundarySeeder::class, 'different province');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Nueva Ecija')->exists());
    }

    public function test_existing_tarlac_and_bulacan_geofences_are_preserved_when_completing_region_three(): void
    {
        $this->seedExistingTarlacBoundaries();
        app(BulacanMunicipalityBoundarySeeder::class)->run();
        $this->assertSame(42, MunicipalityBoundary::query()->active()->count());
        $existingMunicipalities = Municipality::query()->orderBy('id')->get()->map->getAttributes()->all();
        $existingBoundaries = MunicipalityBoundary::query()->orderBy('id')->get()->map->getAttributes()->all();
        $existingAudits = AuditLog::query()->orderBy('id')->get()->map->getAttributes()->all();

        $this->seedRegion();

        $this->assertSame($existingMunicipalities, Municipality::query()
            ->whereIn('id', array_column($existingMunicipalities, 'id'))->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($existingBoundaries, MunicipalityBoundary::query()
            ->whereIn('id', array_column($existingBoundaries, 'id'))->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($existingAudits, AuditLog::query()
            ->whereIn('id', array_column($existingAudits, 'id'))->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(130, Municipality::query()->count());
        $this->assertSame(130, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(112, AuditLog::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_independent_city_scopes_are_not_available_to_neighbouring_province_administrators(): void
    {
        $this->seedRegion();
        $access = app(MunicipalityAccess::class);
        $this->assertCount(88, $access->choices($this->actor));
        $this->assertSame(88, $access->scope(MunicipalityBoundary::query(), $this->actor)->count());

        foreach (['Pampanga' => ['Angeles City', 21], 'Zambales' => ['Olongapo City', 13]] as $province => [$city, $count]) {
            $cityWorkspace = Municipality::query()->where('name', $city)->sole();
            $this->assertSame($this->referenceProvinceId($city), $cityWorkspace->province_id);
            $administrator = new User([
                'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $this->referenceProvinceId($province), 'is_active' => true,
            ]);
            $choices = $access->choices($administrator);
            $this->assertCount($count, $choices);
            $this->assertNotContains($cityWorkspace->id, $choices->modelKeys());
            $this->assertSame($count, $access->scope(MunicipalityBoundary::query(), $administrator)->count());
            $this->assertFalse($access->scope(MunicipalityBoundary::query(), $administrator)
                ->where('municipality_id', $cityWorkspace->id)->exists());
            $this->assertFalse($access->scopeMunicipalities(Municipality::query(), $administrator)
                ->whereKey($cityWorkspace->id)->exists());

            $cityAdministrator = new User([
                'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $cityWorkspace->province_id, 'is_active' => true,
            ]);
            $this->assertSame([$cityWorkspace->id], $access->choices($cityAdministrator)->modelKeys());
            $this->assertSame([$cityWorkspace->id], $access->scope(MunicipalityBoundary::query(), $cityAdministrator)
                ->pluck('municipality_id')->all());
        }
        $this->assertSame(1, User::query()->count());
    }

    public function test_a_city_workspace_assigned_to_a_neighbouring_province_is_not_reassigned(): void
    {
        Province::query()->create(['name' => 'Pampanga', 'is_active' => true]);
        $this->municipality('Pampanga', 'Angeles City', 'ANGELES_CITY');
        $before = $this->databaseState();

        $this->assertImportRejected(AngelesCityBoundarySeeder::class, 'different province');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Angeles City')->exists());
    }

    public function test_an_inactive_late_workspace_is_preserved_and_prior_imports_are_rolled_back(): void
    {
        Province::query()->create(['name' => 'Pampanga', 'is_active' => true]);
        [$name, $identity] = $this->lastIdentity(PampangaMunicipalityBoundarySeeder::class);
        $this->municipality('Pampanga', $identity['workspace_name'] ?? $name, $identity['code'], false);
        $before = $this->databaseState();

        $this->assertImportRejected(PampangaMunicipalityBoundarySeeder::class, 'workspace is inactive');

        $this->assertSame($before, $this->databaseState());
    }

    public function test_a_conflicting_existing_boundary_is_preserved_and_the_province_is_rolled_back(): void
    {
        [$name] = $this->lastIdentity(AuroraMunicipalityBoundarySeeder::class);
        $municipality = $this->municipality('Tarlac', 'Existing approved geofence', 'EXISTING_APPROVED');
        $this->boundaryFromSource($municipality, AuroraMunicipalityBoundarySeeder::class, $name);
        $before = $this->databaseState();

        $this->assertImportRejected(AuroraMunicipalityBoundarySeeder::class, 'conflicts with active boundary');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Aurora')->exists());
    }

    public function test_a_checksum_mismatch_is_rejected_without_changing_files_or_the_database(): void
    {
        $source = new ReflectionClass(BataanMunicipalityBoundarySeeder::class);
        $before = $this->databaseState();

        try {
            app(ReferenceMunicipalityBoundaryImporter::class)->import(
                'Bataan', $source->getConstant('SOURCE_FILE'), str_repeat('0', 64),
                $source->getConstant('SOURCE_REVISION'), $source->getConstant('MUNICIPALITIES')
            );
            $this->fail('A source with the wrong checksum must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('source checksum changed', $exception->getMessage());
        }

        $this->assertSame($before, $this->databaseState());
    }

    public function test_a_provincial_administrator_cannot_create_an_absent_province(): void
    {
        $this->actor->forceFill([
            'role' => User::ROLE_SUPER_ADMIN,
            'province_id' => $this->referenceProvinceId('Tarlac'),
        ])->saveQuietly();
        $before = $this->databaseState();

        $this->assertImportRejected(BataanMunicipalityBoundarySeeder::class, 'account is required');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Bataan')->exists());
    }

    public function test_an_assigned_active_provincial_administrator_can_import_their_existing_province(): void
    {
        $province = Province::query()->create(['name' => 'Aurora', 'is_active' => true]);
        $this->actor->forceFill([
            'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $province->id,
        ])->saveQuietly();

        app(AuroraMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(8, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(8, AuditLog::query()->where('user_id', $this->actor->id)->where('province_id', $province->id)->count());
        $this->assertSame(4, Province::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    private function seedRegion(): void
    {
        foreach (self::SEEDERS as $seeder) {
            app($seeder)->run();
        }
    }

    private function assertImportRejected(string $seeder, string $message): void
    {
        try {
            app($seeder)->run();
            $this->fail('The unsafe import should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function lastIdentity(string $seeder): array
    {
        $identities = (new ReflectionClass($seeder))->getConstant('MUNICIPALITIES');
        $name = array_key_last($identities);

        return [$name, $identities[$name]];
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    private function databaseState(): array
    {
        $state = [];
        foreach ([Province::class, Municipality::class, MunicipalityBoundary::class, AuditLog::class, User::class] as $model) {
            $state[$model] = $model::query()->orderBy('id')->get()->map->getAttributes()->all();
        }

        return $state;
    }

    private function municipality(string $province, string $name, string $code, bool $active = true): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => $name, 'code' => $code, 'province' => $province,
            'province_id' => $this->referenceProvinceId($province), 'is_active' => $active,
        ])->refresh());
    }

    private function boundaryFromSource(Municipality $municipality, string $seeder, string $name): void
    {
        $source = (new ReflectionClass($seeder))->getConstant('SOURCE_FILE');
        $document = json_decode(file_get_contents(database_path($source)), true, 512, JSON_THROW_ON_ERROR);
        $feature = collect($document['features'])->firstWhere('properties.shapeName', $name);
        $this->assertIsArray($feature);
        $this->createExistingBoundary($municipality, $feature['geometry']);
    }

    private function seedExistingTarlacBoundaries(): void
    {
        foreach ([
            'tarlac_reference_boundaries.geojson',
            'tarlac_extended_reference_boundaries.geojson',
            'tarlac_remaining_reference_boundaries.geojson',
        ] as $filename) {
            $document = json_decode(file_get_contents(database_path('seeders/data/'.$filename)), true, 512, JSON_THROW_ON_ERROR);
            foreach ($document['features'] as $feature) {
                $name = $feature['properties']['shapeName'];
                $name = $name === 'City of Tarlac' ? 'Tarlac City' : $name;
                $code = strtoupper(str_replace(' ', '_', $name));
                $this->createExistingBoundary($this->municipality('Tarlac', $name, $code), $feature['geometry']);
            }
        }
    }

    /** @param  array<string,mixed>  $sourceGeometry */
    private function createExistingBoundary(Municipality $municipality, array $sourceGeometry): void
    {
        $geometry = app(GeoGeometry::class)->prepare($sourceGeometry);
        $metadata = app(GeoGeometry::class)->metadata($geometry);

        MunicipalityBoundary::query()->create([
            'municipality_id' => $municipality->id, 'name' => 'Existing approved boundary', 'geojson' => $geometry,
            'color' => '#A855F7', 'status' => MunicipalityBoundary::STATUS_ACTIVE, 'area_ha' => $metadata['area_ha'],
            'centroid_lat' => $metadata['centroid_lat'], 'centroid_lng' => $metadata['centroid_lng'],
            'min_lat' => $metadata['min_lat'], 'max_lat' => $metadata['max_lat'],
            'min_lng' => $metadata['min_lng'], 'max_lng' => $metadata['max_lng'],
            'vertex_count' => $metadata['vertices'], 'created_by' => $this->actor->id,
        ]);
    }
}
