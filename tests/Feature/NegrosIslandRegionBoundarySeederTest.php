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
use Database\Seeders\BacolodCityBoundarySeeder;
use Database\Seeders\NegrosOccidentalMunicipalityBoundarySeeder;
use Database\Seeders\NegrosOrientalMunicipalityBoundarySeeder;
use Database\Seeders\SiquijorMunicipalityBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

class NegrosIslandRegionBoundarySeederTest extends TestCase
{
    use ReferenceProvinceSchema;

    private const SEEDERS = [
        'Negros Occidental' => NegrosOccidentalMunicipalityBoundarySeeder::class,
        'Negros Oriental' => NegrosOrientalMunicipalityBoundarySeeder::class,
        'Siquijor' => SiquijorMunicipalityBoundarySeeder::class,
        'Bacolod City' => BacolodCityBoundarySeeder::class,
    ];

    private const COUNTS = ['Negros Occidental' => 31, 'Negros Oriental' => 25, 'Siquijor' => 6, 'Bacolod City' => 1];

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
            'name' => 'Negros Boundary Administrator', 'email' => 'negros-admin@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true,
        ]));
    }

    public function test_all_63_boundaries_have_current_psgc_identities_attribution_and_correct_supervision(): void
    {
        $this->seedRegion();

        foreach (self::COUNTS as $province => $count) {
            $this->assertTrue(
                Province::query()->where('name', $province)->where('is_active', true)->exists(),
                "{$province} was not created as an active province."
            );
            $this->assertSame(
                $count,
                Municipality::query()->where('province_id', $this->referenceProvinceId($province))->count(),
                "{$province} workspace count is wrong."
            );
            $this->assertSame(
                $count,
                MunicipalityBoundary::query()
                    ->whereIn('municipality_id', Municipality::query()->where('province_id', $this->referenceProvinceId($province))->select('id'))
                    ->where('status', MunicipalityBoundary::STATUS_ACTIVE)
                    ->count(),
                "{$province} active boundary count is wrong."
            );

            $source = new ReflectionClass(self::SEEDERS[$province]);
            $identities = $source->getConstant('MUNICIPALITIES');
            $this->assertCount($count, $identities);
            foreach ($identities as $sourceName => $identity) {
                $municipality = Municipality::query()->where('code', $identity['code'])->sole();
                $this->assertSame($identity['workspace_name'] ?? $sourceName, $municipality->name);
                $this->assertSame($province, $municipality->province);
                $this->assertSame($this->referenceProvinceId($province), $municipality->province_id);
                $this->assertTrue($municipality->is_active);
                $boundary = MunicipalityBoundary::query()->where('municipality_id', $municipality->id)->sole();
                $audit = AuditLog::query()->where('municipality_id', $municipality->id)->sole();
                $this->assertTrue($boundary->isActive());
                $this->assertSame($municipality->name.' Planning Reference · geoBoundaries 2020', $boundary->name);
                $this->assertSame($this->actor->id, $boundary->created_by);
                $this->assertGreaterThan(0, $boundary->area_ha);
                $this->assertGreaterThanOrEqual(3, $boundary->vertex_count);
                $this->assertGreaterThan(8.8, $boundary->min_lat);
                $this->assertLessThan(11.2, $boundary->max_lat);
                $this->assertGreaterThan(122.0, $boundary->min_lng);
                $this->assertLessThan(124.0, $boundary->max_lng);
                $this->assertSame($this->actor->id, $audit->user_id);
                $this->assertSame($this->referenceProvinceId($province), $audit->province_id);
                $this->assertSame('imported', $audit->event);
                $this->assertTrue($audit->metadata['workspace_created']);
                $this->assertSame('planning_reference', $audit->metadata['data_classification']);
                $this->assertSame('9469f09', $audit->metadata['source_revision']);
                $this->assertSame('CC BY 3.0 IGO', $audit->metadata['source_license']);
                $this->assertSame($source->getConstant('SOURCE_CHECKSUM'), $audit->metadata['source_checksum']);
                $this->assertSame($identity['shape_id'], $audit->metadata['source_feature_id']);
                $this->assertSame($identity['psgc_code'], $audit->metadata['psgc_code']);
                $this->assertSame($identity['legacy_psgc_code'], $audit->metadata['legacy_psgc_code']);
                $this->assertMatchesRegularExpression('/^18[0-9]{8}$/', $identity['psgc_code']);
                $legacyPrefix = in_array($province, ['Negros Occidental', 'Bacolod City'], true) ? '06' : '07';
                $this->assertMatchesRegularExpression('/^'.$legacyPrefix.'[0-9]{7}$/', $identity['legacy_psgc_code']);
            }
        }

        $this->assertSame(63, MunicipalityBoundary::query()->where('status', MunicipalityBoundary::STATUS_ACTIVE)->count());
        $this->assertSame(63, AuditLog::query()->where('module', 'Municipality geofences')->count());

        $this->assertSame(
            0,
            AuditLog::query()->where('module', 'Municipality geofences')->whereNull('user_id')->count()
        );
        $this->assertSame(63, Municipality::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertFalse(Schema::hasTable('farmers'));
        $this->assertFalse(Schema::hasTable('rice_seed_distributions'));
    }

    public function test_a_second_run_preserves_records_and_styles_and_invalidates_boundary_caches(): void
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

    public function test_no_two_boundaries_in_the_region_overlap(): void
    {
        $this->seedRegion();

        $geometry = app(GeoGeometry::class);
        $boundaries = MunicipalityBoundary::query()
            ->where('status', MunicipalityBoundary::STATUS_ACTIVE)
            ->get(['id', 'municipality_id', 'geojson']);

        $this->assertCount(63, $boundaries);

        // Neighbouring municipalities share edges; a shared edge is not an overlap. An
        // overlapping interior would put one farm parcel inside two municipalities.
        $overlaps = [];
        for ($i = 0; $i < $boundaries->count(); $i++) {
            for ($j = $i + 1; $j < $boundaries->count(); $j++) {
                $a = $boundaries[$i];
                $b = $boundaries[$j];
                if ($geometry->overlaps($a->geojson, $b->geojson)) {
                    $overlaps[] = $a->municipality_id.' <> '.$b->municipality_id;
                }
            }
        }

        $this->assertSame([], $overlaps, 'Boundaries overlap: '.implode(', ', array_slice($overlaps, 0, 5)));
    }

    public function test_a_municipality_named_the_same_as_one_in_another_province_is_not_taken_over(): void
    {
        // Tarlac already holds a workspace called San Jose, and both `name` and `code`
        // are unique across the whole table. Negros Oriental's San Jose therefore has to
        // arrive under a qualified name rather than claiming the existing workspace.
        $existing = Municipality::query()->create([
            'name' => 'San Jose', 'province' => 'Tarlac', 'code' => 'SAN_JOSE',
            'province_id' => $this->referenceProvinceId('Tarlac'), 'is_active' => true,
        ]);

        (new NegrosOrientalMunicipalityBoundarySeeder)->run();

        $existing->refresh();
        $this->assertSame('Tarlac', $existing->province, 'The Tarlac workspace was reassigned.');
        $this->assertSame('SAN_JOSE', $existing->code);
        $this->assertSame(
            0,
            MunicipalityBoundary::query()->where('municipality_id', $existing->id)->count(),
            'A Negros Oriental boundary was attached to the Tarlac workspace.'
        );

        $negros = Municipality::query()->where('province', 'Negros Oriental')
            ->where('code', 'SAN_JOSE_NEGROS_ORIENTAL')->sole();
        $this->assertSame('San Jose (Negros Oriental)', $negros->name);
    }

    public function test_a_checksum_mismatch_is_rejected_without_changing_files_or_database(): void
    {
        $source = new ReflectionClass(SiquijorMunicipalityBoundarySeeder::class);
        $before = $this->databaseState();

        try {
            app(ReferenceMunicipalityBoundaryImporter::class)->import(
                'Siquijor', $source->getConstant('SOURCE_FILE'), str_repeat('0', 64),
                $source->getConstant('SOURCE_REVISION'), $source->getConstant('MUNICIPALITIES')
            );
            $this->fail('A source with the wrong checksum must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('source checksum changed', $exception->getMessage());
        }

        $this->assertSame($before, $this->databaseState());
    }

    public function test_bacolod_has_its_own_scope_and_existing_workspace_identifiers_remain_compatible(): void
    {
        $this->seedRegion();

        $bacolod = Municipality::query()->where('code', 'BACOLOD_CITY')->sole();
        $this->assertSame('Bacolod City', $bacolod->province);
        $this->assertSame($this->referenceProvinceId('Bacolod City'), $bacolod->province_id);
        $this->assertSame('1830200000', AuditLog::query()->where('municipality_id', $bacolod->id)->sole()->metadata['psgc_code']);

        $sample = [
            'BACOLOD_CITY' => 'Bacolod City',
            'SIQUIJOR' => 'Siquijor',
            'DUMAGUETE_CITY' => 'Dumaguete City',
            'BAYAWAN_CITY' => 'Bayawan City',
        ];

        foreach ($sample as $code => $name) {
            $municipality = Municipality::query()->where('code', $code)->sole();
            $this->assertSame($name, $municipality->name);
            $this->assertTrue(
                MunicipalityBoundary::query()
                    ->where('municipality_id', $municipality->id)
                    ->where('status', MunicipalityBoundary::STATUS_ACTIVE)
                    ->exists(),
                "{$name} has no active boundary."
            );
        }
    }

    public function test_administrators_and_municipal_staff_see_only_their_scope_including_separate_bacolod(): void
    {
        $this->seedRegion();
        $access = app(MunicipalityAccess::class);

        foreach (self::COUNTS as $province => $count) {
            $administrator = new User([
                'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $this->referenceProvinceId($province), 'is_active' => true,
            ]);
            $expectedIds = Municipality::query()->where('province_id', $administrator->province_id)->pluck('id')->sort()->values()->all();
            $this->assertCount($count, $expectedIds);
            $this->assertSame($expectedIds, $access->choices($administrator)->pluck('id')->sort()->values()->all());
            $this->assertSame($expectedIds, $access->scopeMunicipalities(Municipality::query(), $administrator)
                ->pluck('id')->sort()->values()->all());
            $this->assertSame($expectedIds, $access->scope(MunicipalityBoundary::query(), $administrator)
                ->pluck('municipality_id')->sort()->values()->all());

            $staff = new User([
                'role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => $expectedIds[0], 'is_active' => true,
            ]);
            $this->assertSame([$expectedIds[0]], $access->choices($staff)->modelKeys());
            $this->assertSame([$expectedIds[0]], $access->scope(MunicipalityBoundary::query(), $staff)->pluck('municipality_id')->all());
        }

        $this->assertCount(63, $access->choices($this->actor));
        $this->assertSame(63, $access->scope(MunicipalityBoundary::query(), $this->actor)->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_bacolod_already_under_negros_occidental_is_not_silently_reassigned(): void
    {
        Province::query()->create(['name' => 'Negros Occidental', 'is_active' => true]);
        $this->municipality('Negros Occidental', 'Bacolod City', 'BACOLOD_CITY');
        $before = $this->databaseState();

        $this->assertImportRejected(BacolodCityBoundarySeeder::class, 'different province');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Bacolod City')->exists());
    }

    public function test_a_late_identity_conflict_rolls_back_the_whole_province_import(): void
    {
        $identities = (new ReflectionClass(NegrosOccidentalMunicipalityBoundarySeeder::class))->getConstant('MUNICIPALITIES');
        $identity = $identities[array_key_last($identities)];
        $this->municipality('Tarlac', 'Existing unrelated workspace', $identity['psgc_code']);
        $before = $this->databaseState();

        $this->assertImportRejected(NegrosOccidentalMunicipalityBoundarySeeder::class, 'different province');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Negros Occidental')->exists());
    }

    public function test_a_locally_reviewed_boundary_is_preserved_on_repeat_import(): void
    {
        app(SiquijorMunicipalityBoundarySeeder::class)->run();
        MunicipalityBoundary::query()->orderByDesc('id')->firstOrFail()
            ->forceFill(['name' => 'Locally reviewed boundary'])->saveQuietly();
        $before = $this->databaseState();

        $this->assertImportRejected(SiquijorMunicipalityBoundarySeeder::class, 'already has a different active boundary');

        $this->assertSame($before, $this->databaseState());
    }

    public function test_a_negros_occidental_administrator_cannot_import_separate_bacolod_scope(): void
    {
        $province = Province::query()->create(['name' => 'Negros Occidental', 'is_active' => true]);
        $this->actor->forceFill([
            'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $province->id,
        ])->saveQuietly();
        $before = $this->databaseState();

        $this->assertImportRejected(BacolodCityBoundarySeeder::class, 'account is required');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Bacolod City')->exists());
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

    /** @return array<string,array<int,array<string,mixed>>> */
    private function databaseState(): array
    {
        $state = [];
        foreach ([Province::class, Municipality::class, MunicipalityBoundary::class, AuditLog::class, User::class] as $model) {
            $state[$model] = $model::query()->orderBy('id')->get()->map->getAttributes()->all();
        }

        return $state;
    }

    private function municipality(string $province, string $name, string $code): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => $name, 'code' => $code, 'province' => $province,
            'province_id' => $this->referenceProvinceId($province), 'is_active' => true,
        ])->refresh());
    }
}
