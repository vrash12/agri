<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\User;
use App\Support\GeoGeometry;
use Database\Seeders\NegrosOccidentalMunicipalityBoundarySeeder;
use Database\Seeders\NegrosOrientalMunicipalityBoundarySeeder;
use Database\Seeders\SiquijorMunicipalityBoundarySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\ReferenceProvinceSchema;
use Tests\TestCase;

/**
 * The Negros Island Region planning references.
 *
 * Sixty-three boundaries across three provinces, which is more than twice the largest
 * previous import, and the first to reach provinces this system had never held. The
 * checks that matter are not that the rows appear: they are that the right features
 * were taken, that no boundary silently overlaps another, and that a second run leaves
 * the database exactly as the first did.
 */
class NegrosIslandRegionBoundarySeederTest extends TestCase
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
            'name' => 'Negros Boundary Administrator', 'email' => 'negros-admin@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true,
        ]));
    }

    public function test_the_region_imports_as_sixty_three_attributed_boundaries_across_three_provinces(): void
    {
        $this->seedRegion();

        $expected = ['Negros Occidental' => 32, 'Negros Oriental' => 25, 'Siquijor' => 6];

        foreach ($expected as $province => $count) {
            $this->assertTrue(
                Province::query()->where('name', $province)->where('is_active', true)->exists(),
                "{$province} was not created as an active province."
            );
            $this->assertSame(
                $count,
                Municipality::query()->where('province', $province)->count(),
                "{$province} workspace count is wrong."
            );
            $this->assertSame(
                $count,
                MunicipalityBoundary::query()
                    ->whereIn('municipality_id', Municipality::query()->where('province', $province)->select('id'))
                    ->where('status', MunicipalityBoundary::STATUS_ACTIVE)
                    ->count(),
                "{$province} active boundary count is wrong."
            );
        }

        $this->assertSame(63, MunicipalityBoundary::query()->where('status', MunicipalityBoundary::STATUS_ACTIVE)->count());
        $this->assertSame(63, AuditLog::query()->where('module', 'Municipality geofences')->count());

        // Every import is attributable. An unattributed boundary change is one nobody
        // can account for later.
        $this->assertSame(
            0,
            AuditLog::query()->where('module', 'Municipality geofences')->whereNull('user_id')->count()
        );
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->seedRegion();

        $before = [
            'municipalities' => Municipality::query()->count(),
            'boundaries' => MunicipalityBoundary::query()->count(),
            'audits' => AuditLog::query()->count(),
        ];

        $this->seedRegion();

        $this->assertSame($before['municipalities'], Municipality::query()->count(), 'A repeat run created workspaces.');
        $this->assertSame($before['boundaries'], MunicipalityBoundary::query()->count(), 'A repeat run created boundaries.');
        $this->assertSame($before['audits'], AuditLog::query()->count(), 'A repeat run recorded a second import.');
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

    public function test_a_changed_source_file_stops_the_import(): void
    {
        // The checksum is the only thing standing between this import and a geometry
        // somebody edited by hand after it was reviewed.
        $path = database_path('seeders/data/siquijor_municipality_reference_boundaries.geojson');
        $original = file_get_contents($path);

        try {
            $document = json_decode($original, true);
            $document['features'][0]['properties']['shapeName'] = 'Tampered';
            file_put_contents($path, json_encode($document));

            $this->expectException(RuntimeException::class);
            (new SiquijorMunicipalityBoundarySeeder)->run();
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_every_boundary_keeps_the_psgc_identity_it_was_imported_with(): void
    {
        $this->seedRegion();

        // Bacolod City is a highly urbanized city, administratively independent of the
        // province. It is stored under Negros Occidental because that is where it sits
        // and because Baguio City is already recorded under Benguet the same way.
        $bacolod = Municipality::query()->where('code', 'BACOLOD_CITY')->sole();
        $this->assertSame('Negros Occidental', $bacolod->province);

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

    private function seedRegion(): void
    {
        (new NegrosOccidentalMunicipalityBoundarySeeder)->run();
        (new NegrosOrientalMunicipalityBoundarySeeder)->run();
        (new SiquijorMunicipalityBoundarySeeder)->run();
    }
}
