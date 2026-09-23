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
use Database\Seeders\MarinduqueMunicipalityBoundarySeeder;
use Database\Seeders\OccidentalMindoroMunicipalityBoundarySeeder;
use Database\Seeders\OrientalMindoroMunicipalityBoundarySeeder;
use Database\Seeders\PalawanMunicipalityBoundarySeeder;
use Database\Seeders\PuertoPrincesaCityBoundarySeeder;
use Database\Seeders\RomblonMunicipalityBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

class MimaropaBoundarySeederTest extends TestCase
{
    use ReferenceProvinceSchema;

    private const SEEDERS = [
        'Marinduque' => MarinduqueMunicipalityBoundarySeeder::class,
        'Occidental Mindoro' => OccidentalMindoroMunicipalityBoundarySeeder::class,
        'Palawan' => PalawanMunicipalityBoundarySeeder::class,
        'Oriental Mindoro' => OrientalMindoroMunicipalityBoundarySeeder::class,
        'Romblon' => RomblonMunicipalityBoundarySeeder::class,
        'Puerto Princesa City' => PuertoPrincesaCityBoundarySeeder::class,
    ];

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
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
        (require database_path('migrations/2026_09_21_000100_add_fill_opacity_to_municipality_boundaries.php'))->up();
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        $this->createProvinceSchema();
        $this->actor = User::withoutEvents(fn () => User::query()->create([
            'name' => 'Mimaropa Boundary Administrator', 'email' => 'mimaropa-admin@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true,
        ]));
    }

    public function test_all_73_boundaries_have_the_correct_supervision_and_attribution(): void
    {
        $this->seedRegion();

        foreach (['Marinduque' => 6, 'Occidental Mindoro' => 11, 'Palawan' => 23, 'Oriental Mindoro' => 15, 'Romblon' => 17, 'Puerto Princesa City' => 1] as $name => $count) {
            $province = Province::query()->where('name', $name)->sole();
            $this->assertTrue($province->is_active);
            $municipalities = Municipality::query()->where('province_id', $province->id)->get();
            $this->assertCount($count, $municipalities, "Incorrect {$name} municipality count.");
            $source = new ReflectionClass(self::SEEDERS[$name]);

            foreach ($municipalities as $municipality) {
                $boundary = MunicipalityBoundary::query()->where('municipality_id', $municipality->id)->sole();
                $audit = AuditLog::query()->where('municipality_id', $municipality->id)->sole();
                $this->assertSame($name, $municipality->province);
                $this->assertTrue($municipality->is_active);
                $this->assertTrue($boundary->isActive());
                $this->assertSame($municipality->name.' Planning Reference · geoBoundaries 2020', $boundary->name);
                $this->assertSame($this->actor->id, $boundary->created_by);
                $this->assertGreaterThan(0, $boundary->area_ha);
                $this->assertGreaterThanOrEqual(3, $boundary->vertex_count);
                $this->assertSame($this->actor->id, $audit->user_id);
                $this->assertSame((int) $province->id, $audit->province_id);
                $this->assertSame('imported', $audit->event);
                $this->assertSame('Municipality geofences', $audit->module);
                $this->assertTrue($audit->metadata['workspace_created']);
                $this->assertSame('planning_reference', $audit->metadata['data_classification']);
                $this->assertSame('9469f09', $audit->metadata['source_revision']);
                $this->assertSame('CC BY 3.0 IGO', $audit->metadata['source_license']);
                $this->assertSame($source->getConstant('SOURCE_CHECKSUM'), $audit->metadata['source_checksum']);
                $this->assertMatchesRegularExpression('/^17[0-9]{8}$/', $audit->metadata['psgc_code']);
                $this->assertMatchesRegularExpression('/^17[0-9]{7}$/', $audit->metadata['legacy_psgc_code']);
            }
        }

        $this->assertSame(73, Municipality::query()->count());
        $this->assertSame(73, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(73, AuditLog::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertFalse(Schema::hasTable('farmers'));
        $this->assertFalse(Schema::hasTable('rice_seed_distributions'));
    }

    public function test_repeating_the_import_preserves_ids_geometry_styles_and_audits(): void
    {
        $this->seedRegion();
        MunicipalityBoundary::query()->firstOrFail()->forceFill(['color' => '#A855F7', 'fill_opacity' => 0.37])->saveQuietly();
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

    public function test_puerto_princesa_city_scope_is_separate_from_palawan_administrators(): void
    {
        app(PalawanMunicipalityBoundarySeeder::class)->run();
        app(PuertoPrincesaCityBoundarySeeder::class)->run();
        $city = Municipality::query()->where('name', 'Puerto Princesa City')->sole();
        $this->assertSame($this->referenceProvinceId('Puerto Princesa City'), $city->province_id);
        $access = app(MunicipalityAccess::class);
        $palawanAdministrator = new User([
            'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $this->referenceProvinceId('Palawan'), 'is_active' => true,
        ]);
        $this->assertCount(23, $access->choices($palawanAdministrator));
        $this->assertNotContains($city->id, $access->choices($palawanAdministrator)->modelKeys());
        $this->assertSame(23, $access->scope(MunicipalityBoundary::query(), $palawanAdministrator)->count());
        $this->assertFalse($access->scope(MunicipalityBoundary::query(), $palawanAdministrator)
            ->where('municipality_id', $city->id)->exists());
        $this->assertFalse($access->scopeMunicipalities(Municipality::query(), $palawanAdministrator)
            ->whereKey($city->id)->exists());

        $cityAdministrator = new User([
            'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $city->province_id, 'is_active' => true,
        ]);
        $this->assertSame([$city->id], $access->choices($cityAdministrator)->modelKeys());
        $this->assertSame([$city->id], $access->scope(MunicipalityBoundary::query(), $cityAdministrator)
            ->pluck('municipality_id')->all());
        $this->assertCount(24, $access->choices($this->actor));
        $this->assertSame(1, User::query()->count());
    }

    public function test_an_existing_puerto_princesa_workspace_under_palawan_is_not_reassigned(): void
    {
        Province::query()->create(['name' => 'Palawan', 'is_active' => true]);
        $this->municipality('Palawan', 'Puerto Princesa City', 'PUERTO_PRINCESA_CITY');
        $before = $this->databaseState();

        $this->assertImportRejected(PuertoPrincesaCityBoundarySeeder::class, 'different province');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Puerto Princesa City')->exists());
    }

    public function test_calapan_remains_a_component_city_under_oriental_mindoro(): void
    {
        app(OrientalMindoroMunicipalityBoundarySeeder::class)->run();
        $city = Municipality::query()->where('name', 'Calapan City')->sole();
        $this->assertSame($this->referenceProvinceId('Oriental Mindoro'), $city->province_id);
        $administrator = new User(['role' => User::ROLE_SUPER_ADMIN, 'province_id' => $city->province_id, 'is_active' => true]);
        $choices = app(MunicipalityAccess::class)->choices($administrator);
        $this->assertCount(15, $choices);
        $this->assertContains($city->id, $choices->modelKeys());
        $this->assertFalse(Province::query()->where('name', 'Calapan City')->exists());
    }

    public function test_a_late_psgc_match_in_another_province_rolls_back_the_whole_province(): void
    {
        [, $identity] = $this->lastIdentity(OccidentalMindoroMunicipalityBoundarySeeder::class);
        $this->municipality('Tarlac', 'Existing unrelated office', $identity['psgc_code']);
        $before = $this->databaseState();

        $this->assertImportRejected(OccidentalMindoroMunicipalityBoundarySeeder::class, 'different province');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Occidental Mindoro')->exists());
    }

    public function test_an_inactive_late_workspace_is_preserved_and_prior_imports_are_rolled_back(): void
    {
        Province::query()->create(['name' => 'Oriental Mindoro', 'is_active' => true]);
        [$name, $identity] = $this->lastIdentity(OrientalMindoroMunicipalityBoundarySeeder::class);
        $this->municipality('Oriental Mindoro', $identity['workspace_name'] ?? $name, $identity['code'], false);
        $before = $this->databaseState();

        $this->assertImportRejected(OrientalMindoroMunicipalityBoundarySeeder::class, 'workspace is inactive');

        $this->assertSame($before, $this->databaseState());
    }

    public function test_a_conflicting_existing_boundary_is_preserved_and_the_province_is_rolled_back(): void
    {
        [$name] = $this->lastIdentity(RomblonMunicipalityBoundarySeeder::class);
        $municipality = $this->municipality('Tarlac', 'Existing approved geofence', 'EXISTING_APPROVED');
        $this->boundaryFromSource($municipality, RomblonMunicipalityBoundarySeeder::class, $name);
        $before = $this->databaseState();

        $this->assertImportRejected(RomblonMunicipalityBoundarySeeder::class, 'conflicts with active boundary');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Romblon')->exists());
    }

    public function test_a_changed_existing_reference_is_not_overwritten(): void
    {
        app(MarinduqueMunicipalityBoundarySeeder::class)->run();
        MunicipalityBoundary::query()->orderByDesc('id')->firstOrFail()
            ->forceFill(['name' => 'Locally reviewed boundary'])->saveQuietly();
        $before = $this->databaseState();

        $this->assertImportRejected(MarinduqueMunicipalityBoundarySeeder::class, 'already has a different active boundary');

        $this->assertSame($before, $this->databaseState());
    }

    public function test_a_checksum_mismatch_is_rejected_without_database_changes(): void
    {
        $source = new ReflectionClass(MarinduqueMunicipalityBoundarySeeder::class);
        $before = $this->databaseState();

        try {
            app(ReferenceMunicipalityBoundaryImporter::class)->import(
                'Marinduque', $source->getConstant('SOURCE_FILE'), str_repeat('0', 64),
                $source->getConstant('SOURCE_REVISION'), $source->getConstant('MUNICIPALITIES')
            );
            $this->fail('A source with the wrong checksum must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('source checksum changed', $exception->getMessage());
        }

        $this->assertSame($before, $this->databaseState());
    }

    public function test_a_provincial_administrator_cannot_import_another_province(): void
    {
        $province = Province::query()->create(['name' => 'Palawan', 'is_active' => true]);
        $this->actor->forceFill([
            'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $province->id,
        ])->saveQuietly();
        $before = $this->databaseState();

        $this->assertImportRejected(PuertoPrincesaCityBoundarySeeder::class, 'account is required');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Puerto Princesa City')->exists());
    }

    public function test_an_assigned_active_provincial_administrator_can_import_their_existing_province(): void
    {
        $province = Province::query()->create(['name' => 'Marinduque', 'is_active' => true]);
        $this->actor->forceFill([
            'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $province->id,
        ])->saveQuietly();

        app(MarinduqueMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(6, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(6, AuditLog::query()->where('user_id', $this->actor->id)->where('province_id', $province->id)->count());
        $this->assertSame(4, Province::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    private function seedRegion(): void
    {
        app(\Database\Seeders\MimaropaBoundarySeeder::class)->run();
    }

    public function test_a_conflict_in_the_last_province_rolls_back_the_entire_region(): void
    {
        [, $identity] = $this->lastIdentity(RomblonMunicipalityBoundarySeeder::class);
        $this->municipality('Tarlac', 'Unrelated existing office', $identity['psgc_code']);
        $before = $this->databaseState();

        $this->assertImportRejected(\Database\Seeders\MimaropaBoundarySeeder::class, 'different province');

        $this->assertSame($before, $this->databaseState());
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
        $geometry = app(GeoGeometry::class)->prepare($feature['geometry']);
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
