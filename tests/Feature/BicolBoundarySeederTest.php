<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\User;
use App\Support\MunicipalityAccess;
use App\Support\ReferenceMunicipalityBoundaryImporter;
use Database\Seeders\AlbayMunicipalityBoundarySeeder;
use Database\Seeders\BicolBoundarySeeder;
use Database\Seeders\CamarinesNorteMunicipalityBoundarySeeder;
use Database\Seeders\CamarinesSurMunicipalityBoundarySeeder;
use Database\Seeders\CatanduanesMunicipalityBoundarySeeder;
use Database\Seeders\MasbateMunicipalityBoundarySeeder;
use Database\Seeders\NagaCityBoundarySeeder;
use Database\Seeders\SorsogonMunicipalityBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

class BicolBoundarySeederTest extends TestCase
{
    use ReferenceProvinceSchema;

    /** @var array<string,class-string> */
    private const SEEDERS = [
        'Albay' => AlbayMunicipalityBoundarySeeder::class,
        'Camarines Norte' => CamarinesNorteMunicipalityBoundarySeeder::class,
        'Camarines Sur' => CamarinesSurMunicipalityBoundarySeeder::class,
        'Catanduanes' => CatanduanesMunicipalityBoundarySeeder::class,
        'Masbate' => MasbateMunicipalityBoundarySeeder::class,
        'Naga City' => NagaCityBoundarySeeder::class,
        'Sorsogon' => SorsogonMunicipalityBoundarySeeder::class,
    ];

    /** @var array<string,int> */
    private const COUNTS = [
        'Albay' => 18,
        'Camarines Norte' => 12,
        'Camarines Sur' => 36,
        'Catanduanes' => 11,
        'Masbate' => 21,
        'Naga City' => 1,
        'Sorsogon' => 15,
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
            'name' => 'Bicol Boundary Administrator', 'email' => 'bicol-admin@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true,
        ]));
    }

    public function test_all_114_boundaries_have_expected_scopes_and_attribution(): void
    {
        $this->seedRegion();

        foreach (self::COUNTS as $provinceName => $count) {
            $province = Province::query()->where('name', $provinceName)->sole();
            $this->assertTrue($province->is_active);
            $municipalities = Municipality::query()->where('province_id', $province->id)->get();
            $this->assertCount($count, $municipalities, "Incorrect {$provinceName} workspace count.");
            $source = new ReflectionClass(self::SEEDERS[$provinceName]);

            foreach ($municipalities as $municipality) {
                $boundary = MunicipalityBoundary::query()->where('municipality_id', $municipality->id)->sole();
                $audit = AuditLog::query()->where('municipality_id', $municipality->id)->sole();
                $this->assertSame($provinceName, $municipality->province);
                $this->assertTrue($boundary->isActive());
                $this->assertSame($municipality->name.' Planning Reference · geoBoundaries 2020', $boundary->name);
                $this->assertSame($this->actor->id, $boundary->created_by);
                $this->assertGreaterThan(0, $boundary->area_ha);
                $this->assertGreaterThanOrEqual(3, $boundary->vertex_count);
                $this->assertSame($this->actor->id, $audit->user_id);
                $this->assertSame((int) $province->id, $audit->province_id);
                $this->assertSame('imported', $audit->event);
                $this->assertTrue($audit->metadata['workspace_created']);
                $this->assertSame('planning_reference', $audit->metadata['data_classification']);
                $this->assertSame('9469f09', $audit->metadata['source_revision']);
                $this->assertSame('CC BY 3.0 IGO', $audit->metadata['source_license']);
                $this->assertSame($source->getConstant('SOURCE_CHECKSUM'), $audit->metadata['source_checksum']);
                $this->assertMatchesRegularExpression('/^05[0-9]{8}$/', $audit->metadata['psgc_code']);
                $this->assertMatchesRegularExpression('/^05[0-9]{7}$/', $audit->metadata['legacy_psgc_code']);
            }
        }

        $this->assertSame(114, Municipality::query()->count());
        $this->assertSame(114, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(114, AuditLog::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_naga_isolation_and_iriga_component_city_inclusion(): void
    {
        app(CamarinesSurMunicipalityBoundarySeeder::class)->run();
        app(NagaCityBoundarySeeder::class)->run();

        $camarinesSur = Province::query()->where('name', 'Camarines Sur')->sole();
        $naga = Province::query()->where('name', 'Naga City')->sole();
        $iriga = Municipality::query()->where('province_id', $camarinesSur->id)->where('name', 'like', 'Iriga%')->sole();
        $nagaWorkspace = Municipality::query()->where('province_id', $naga->id)->sole();

        $this->assertSame('Camarines Sur', $iriga->province);
        $this->assertSame(36, Municipality::query()->where('province_id', $camarinesSur->id)->count());
        $this->assertSame(1, MunicipalityBoundary::query()->where('municipality_id', $iriga->id)->count());
        $this->assertSame('Naga City', $nagaWorkspace->name);

        $access = app(MunicipalityAccess::class);
        $camarinesAdmin = new User(['role' => User::ROLE_SUPER_ADMIN, 'province_id' => $camarinesSur->id, 'is_active' => true]);
        $nagaAdmin = new User(['role' => User::ROLE_SUPER_ADMIN, 'province_id' => $naga->id, 'is_active' => true]);
        $this->assertCount(36, $access->choices($camarinesAdmin));
        $this->assertNotContains($nagaWorkspace->id, $access->choices($camarinesAdmin)->modelKeys());
        $this->assertSame([$nagaWorkspace->id], $access->choices($nagaAdmin)->modelKeys());
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

    public function test_qualified_duplicate_names_preserve_existing_workspaces(): void
    {
        [$province, $seeder, $identity] = $this->qualifiedIdentity();
        $rawName = array_key_first(array_filter(
            (new ReflectionClass($seeder))->getConstant('MUNICIPALITIES'),
            fn (array $value): bool => ($value['workspace_name'] ?? null) === $identity['workspace_name']
        ));
        $this->assertNotEmpty($rawName);
        $existing = $this->municipality('Tarlac', $rawName, 'BICOL-DUPLICATE-FIXTURE');

        $this->seedRegion();

        $this->assertSame($rawName, $existing->fresh()->name);
        $workspace = Municipality::query()->where('name', $identity['workspace_name'])
            ->where('province_id', $this->referenceProvinceId($province))->sole();
        $this->assertSame(1, MunicipalityBoundary::query()->where('municipality_id', $workspace->id)->count());
    }

    public function test_a_conflict_in_the_last_province_rolls_back_the_entire_region(): void
    {
        [, $identity] = $this->lastIdentity(SorsogonMunicipalityBoundarySeeder::class);
        $this->municipality('Tarlac', 'Unrelated existing office', $identity['psgc_code']);
        $before = $this->databaseState();

        $this->assertImportRejected(BicolBoundarySeeder::class, 'different province');

        $this->assertSame($before, $this->databaseState());
        foreach (array_keys(self::COUNTS) as $province) {
            $this->assertFalse(Province::query()->where('name', $province)->exists());
        }
    }

    public function test_an_assigned_active_albay_administrator_can_import_their_province(): void
    {
        $province = Province::query()->create(['name' => 'Albay', 'is_active' => true]);
        $this->actor->forceFill(['role' => User::ROLE_SUPER_ADMIN, 'province_id' => $province->id])->saveQuietly();

        app(AlbayMunicipalityBoundarySeeder::class)->run();

        $this->assertSame(18, MunicipalityBoundary::query()->active()->count());
        $this->assertSame(18, AuditLog::query()->where('user_id', $this->actor->id)->where('province_id', $province->id)->count());
    }

    public function test_a_provincial_administrator_cannot_import_naga_city_scope(): void
    {
        $province = Province::query()->create(['name' => 'Albay', 'is_active' => true]);
        $this->actor->forceFill(['role' => User::ROLE_SUPER_ADMIN, 'province_id' => $province->id])->saveQuietly();
        $before = $this->databaseState();

        $this->assertImportRejected(NagaCityBoundarySeeder::class, 'account is required');

        $this->assertSame($before, $this->databaseState());
        $this->assertFalse(Province::query()->where('name', 'Naga City')->exists());
    }

    public function test_a_checksum_mismatch_is_rejected_without_database_changes(): void
    {
        $source = new ReflectionClass(AlbayMunicipalityBoundarySeeder::class);
        $before = $this->databaseState();

        try {
            app(ReferenceMunicipalityBoundaryImporter::class)->import(
                'Albay', $source->getConstant('SOURCE_FILE'), str_repeat('0', 64),
                $source->getConstant('SOURCE_REVISION'), $source->getConstant('MUNICIPALITIES')
            );
            $this->fail('A source with the wrong checksum must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('source checksum changed', $exception->getMessage());
        }

        $this->assertSame($before, $this->databaseState());
    }

    /** @return array{0:string,1:class-string,2:array<string,mixed>} */
    private function qualifiedIdentity(): array
    {
        foreach (self::SEEDERS as $province => $seeder) {
            foreach ((new ReflectionClass($seeder))->getConstant('MUNICIPALITIES') as $name => $identity) {
                if (isset($identity['workspace_name'])) {
                    return [$province, $seeder, ['source_name' => $name, ...$identity]];
                }
            }
        }
        $this->fail('Bicol source identities must qualify nationally repeated names.');
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function lastIdentity(string $seeder): array
    {
        $identities = (new ReflectionClass($seeder))->getConstant('MUNICIPALITIES');
        $name = array_key_last($identities);

        return [$name, $identities[$name]];
    }

    private function seedRegion(): void
    {
        app(BicolBoundarySeeder::class)->run();
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

    private function municipality(string $province, string $name, string $code, bool $active = true): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::query()->create([
            'name' => $name, 'code' => $code, 'province' => $province,
            'province_id' => $this->referenceProvinceId($province), 'is_active' => $active,
        ])->refresh());
    }
}
