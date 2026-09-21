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
use Database\Seeders\AbraMunicipalityBoundarySeeder;
use Database\Seeders\ApayaoMunicipalityBoundarySeeder;
use Database\Seeders\BaguioCityBoundarySeeder;
use Database\Seeders\BenguetMunicipalityBoundarySeeder;
use Database\Seeders\BenguetRemainingMunicipalityBoundarySeeder;
use Database\Seeders\IfugaoMunicipalityBoundarySeeder;
use Database\Seeders\KalingaMunicipalityBoundarySeeder;
use Database\Seeders\MountainProvinceMunicipalityBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

class RemainingCarBoundarySeederTest extends TestCase
{
    use ReferenceProvinceSchema;

    private const SEEDERS = [
        'Abra' => AbraMunicipalityBoundarySeeder::class,
        'Apayao' => ApayaoMunicipalityBoundarySeeder::class,
        'Ifugao' => IfugaoMunicipalityBoundarySeeder::class,
        'Kalinga' => KalingaMunicipalityBoundarySeeder::class,
    ];

    private const COUNTS = ['Abra' => 27, 'Apayao' => 7, 'Ifugao' => 11, 'Kalinga' => 8];

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
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        $this->createProvinceSchema();
        $this->actor = User::withoutEvents(fn () => User::query()->create([
            'name' => 'CAR Boundary Administrator', 'email' => 'car-admin@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true,
        ]));
    }

    public function test_all_fifty_three_boundaries_have_correct_ownership_identity_and_attribution(): void
    {
        $this->seedRemainingCar();

        foreach (self::COUNTS as $name => $count) {
            $province = Province::query()->where('name', $name)->sole();
            $this->assertTrue($province->is_active);
            $municipalities = Municipality::query()->where('province_id', $province->id)->get();
            $this->assertCount($count, $municipalities, "Incorrect {$name} municipality count.");
            $source = new ReflectionClass(self::SEEDERS[$name]);
            $identities = $source->getConstant('MUNICIPALITIES');

            foreach ($identities as $sourceName => $identity) {
                $municipality = $municipalities->firstWhere('name', $identity['workspace_name'] ?? $sourceName);
                $this->assertInstanceOf(Municipality::class, $municipality);
                $boundary = MunicipalityBoundary::query()->where('municipality_id', $municipality->id)->sole();
                $audit = AuditLog::query()->where('municipality_id', $municipality->id)->sole();
                $this->assertSame($name, $municipality->province);
                $this->assertSame($identity['code'], $municipality->code);
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
                $this->assertSame($identity['shape_id'], $audit->metadata['source_feature_id']);
                $this->assertSame($identity['psgc_code'], $audit->metadata['psgc_code']);
                $this->assertSame($identity['legacy_psgc_code'], $audit->metadata['legacy_psgc_code']);
                $this->assertMatchesRegularExpression('/^14[0-9]{8}$/', $audit->metadata['psgc_code']);
                $this->assertMatchesRegularExpression('/^14[0-9]{7}$/', $audit->metadata['legacy_psgc_code']);
            }
        }

        $this->assertSame(53, Municipality::query()->count());
        $this->assertSame(53, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(53, AuditLog::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertFalse(Schema::hasTable('farmers'));
        $this->assertFalse(Schema::hasTable('rice_seed_distributions'));
    }

    public function test_remaining_references_coexist_with_and_preserve_the_existing_car_boundaries(): void
    {
        foreach ([
            BenguetMunicipalityBoundarySeeder::class,
            BenguetRemainingMunicipalityBoundarySeeder::class,
            BaguioCityBoundarySeeder::class,
            MountainProvinceMunicipalityBoundarySeeder::class,
        ] as $seeder) {
            app($seeder)->run();
        }
        $this->assertSame(24, MunicipalityBoundary::query()->active()->count());
        $before = $this->databaseState();

        $this->seedRemainingCar();

        foreach ($this->databaseState() as $model => $records) {
            $this->assertSame($before[$model], array_slice($records, 0, count($before[$model])));
        }
        $this->assertSame(77, Municipality::query()->count());
        $this->assertSame(77, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(77, AuditLog::query()->count());
    }

    public function test_repeating_all_four_imports_preserves_existing_records_and_clears_boundary_caches(): void
    {
        $this->seedRemainingCar();
        MunicipalityBoundary::query()->firstOrFail()->forceFill(['color' => '#A855F7'])->saveQuietly();
        $before = $this->databaseState();
        foreach (Municipality::query()->pluck('id') as $id) {
            Cache::put('municipality-boundary:active:v1:'.$id, 'stale');
        }

        $this->travel(1)->minutes();
        $this->seedRemainingCar();

        $this->assertSame($before, $this->databaseState());
        foreach (Municipality::query()->pluck('id') as $id) {
            $this->assertFalse(Cache::has('municipality-boundary:active:v1:'.$id));
        }
    }

    /** @dataProvider provinceSeeders */
    public function test_a_late_psgc_match_in_another_province_rolls_back_the_whole_import(string $province, string $seeder): void
    {
        [, $identity] = $this->lastIdentity($seeder);
        $this->municipality('Tarlac', 'Existing unrelated office', $identity['psgc_code']);
        $before = $this->databaseState();

        $this->assertImportRejected($seeder, 'different province');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', $province)->exists());
    }

    /** @dataProvider provinceSeeders */
    public function test_an_inactive_late_workspace_is_preserved_and_earlier_inserts_are_rolled_back(string $province, string $seeder): void
    {
        Province::query()->create(['name' => $province, 'is_active' => true]);
        [$name, $identity] = $this->lastIdentity($seeder);
        $this->municipality($province, $identity['workspace_name'] ?? $name, $identity['code'], false);
        $before = $this->databaseState();

        $this->assertImportRejected($seeder, 'workspace is inactive');

        $this->assertSame($before, $this->databaseState());
    }

    /** @dataProvider provinceSeeders */
    public function test_an_existing_overlapping_boundary_is_preserved_and_the_province_is_rolled_back(string $province, string $seeder): void
    {
        [$name] = $this->lastIdentity($seeder);
        $municipality = $this->municipality('Tarlac', 'Existing approved geofence', 'EXISTING_APPROVED');
        $this->boundaryFromSource($municipality, $seeder, $name);
        $before = $this->databaseState();

        $this->assertImportRejected($seeder, 'conflicts with active boundary');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', $province)->exists());
    }

    /** @dataProvider provinceSeeders */
    public function test_an_edited_reference_is_never_overwritten(string $province, string $seeder): void
    {
        app($seeder)->run();
        MunicipalityBoundary::query()->orderByDesc('id')->firstOrFail()
            ->forceFill(['name' => 'Locally reviewed boundary'])->saveQuietly();
        $before = $this->databaseState();

        $this->assertImportRejected($seeder, 'already has a different active boundary');

        $this->assertSame($before, $this->databaseState());
    }

    /** @dataProvider provinceSeeders */
    public function test_a_foreign_provincial_administrator_cannot_run_the_import(string $province, string $seeder): void
    {
        $this->actor->forceFill([
            'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $this->referenceProvinceId('Benguet'),
        ])->saveQuietly();
        $before = $this->databaseState();

        $this->assertImportRejected($seeder, 'account is required');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', $province)->exists());
    }

    public function test_a_source_checksum_mismatch_is_rejected_before_any_database_write(): void
    {
        $before = $this->databaseState();
        foreach (self::SEEDERS as $province => $seeder) {
            $source = new ReflectionClass($seeder);
            try {
                app(ReferenceMunicipalityBoundaryImporter::class)->import(
                    $province, $source->getConstant('SOURCE_FILE'), str_repeat('0', 64),
                    $source->getConstant('SOURCE_REVISION'), $source->getConstant('MUNICIPALITIES')
                );
                $this->fail('A source with the wrong checksum must be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('source checksum changed', $exception->getMessage());
            }
        }

        $this->assertSame($before, $this->databaseState());
    }

    public function test_provincial_scope_is_isolated_and_tabuk_remains_a_kalinga_component_city(): void
    {
        $this->seedRemainingCar();
        $access = app(MunicipalityAccess::class);
        foreach (self::COUNTS as $name => $count) {
            $province = Province::where('name', $name)->sole();
            $admin = new User(['role' => User::ROLE_SUPER_ADMIN, 'province_id' => $province->id, 'is_active' => true]);
            $this->assertCount($count, $access->choices($admin));
            $this->assertSame([$province->id], $access->choices($admin)->pluck('province_id')->unique()->values()->all());
            $this->assertSame($count, $access->scope(MunicipalityBoundary::query(), $admin)->count());
        }
        $benguetAdmin = new User([
            'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $this->referenceProvinceId('Benguet'), 'is_active' => true,
        ]);
        $this->assertCount(0, $access->choices($benguetAdmin));
        $this->assertSame(0, $access->scope(MunicipalityBoundary::query(), $benguetAdmin)->count());
        $tabuk = Municipality::where('name', 'Tabuk City')->sole();
        $this->assertSame(Province::where('name', 'Kalinga')->sole()->id, $tabuk->province_id);
        $this->assertFalse(Province::where('name', 'Tabuk City')->exists());
    }

    public function test_shared_municipality_names_do_not_capture_or_change_another_provinces_workspace(): void
    {
        $otherNames = [
            'Dolores', 'La Paz', 'Pilar', 'San Isidro', 'San Juan', 'San Quintin', 'Luna', 'Rizal',
        ];
        $others = collect($otherNames)->map(fn (string $name) => $this->municipality(
            'Tarlac', $name, strtoupper(str_replace(' ', '_', $name))
        ));
        $before = $others->map->getAttributes()->all();

        $this->seedRemainingCar();

        $this->assertSame($before, $others->map(fn (Municipality $municipality) => $municipality->fresh()->getAttributes())->all());
        $this->assertSame(0, MunicipalityBoundary::whereIn('municipality_id', $others->pluck('id'))->count());
        foreach (self::SEEDERS as $province => $seeder) {
            $identities = (new ReflectionClass($seeder))->getConstant('MUNICIPALITIES');
            foreach (array_intersect(array_keys($identities), $otherNames) as $name) {
                $identity = $identities[$name];
                $this->assertArrayHasKey('workspace_name', $identity);
                $this->assertNotSame($name, $identity['workspace_name']);
                $municipality = Municipality::where('name', $identity['workspace_name'])->sole();
                $this->assertSame($this->referenceProvinceId($province), $municipality->province_id);
            }
        }
        $this->assertSame(53, MunicipalityBoundary::query()->active()->count());
    }

    /** @return array<string,array{0:string,1:class-string}> */
    public static function provinceSeeders(): array
    {
        $cases = [];
        foreach (self::SEEDERS as $province => $seeder) {
            $cases[$province] = [$province, $seeder];
        }

        return $cases;
    }

    private function seedRemainingCar(): void
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
