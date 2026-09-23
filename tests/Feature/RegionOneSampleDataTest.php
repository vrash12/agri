<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\Region;
use App\Models\RiceDistributionBatch;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\GeoGeometry;
use App\Support\MunicipalityAccess;
use App\Support\RegionOneSampleData;
use App\Support\SeedReleaseQuantity;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class RegionOneSampleDataTest extends TestCase
{
    private User $owner;

    private Region $region;

    /** @var array<int, Municipality> */
    private array $municipalities = [];

    /** @var array<int, string> */
    private const OPERATIONAL_TABLES = ['farmers', 'rice_distribution_batches', 'rice_seed_distributions', 'farm_plots'];

    protected function setUp(): void
    {
        parent::setUp();

        // Establish a disposable connection before creating fixtures or making any query.
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Cache::clear();
        $this->createSchema();

        $this->owner = User::withoutEvents(fn () => User::create([
            'name' => 'Sample import operator', 'email' => 'sample-owner@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_SYSTEM_OWNER,
            'is_active' => true,
        ]));
        $this->region = Region::create([
            'code' => 'region1', 'name' => 'Region I — Ilocos Region', 'is_active' => true,
        ]);

        foreach (['Ilocos Norte', 'Ilocos Sur', 'La Union'] as $index => $name) {
            $province = Province::create(['name' => $name, 'region_id' => $this->region->id, 'is_active' => true]);
            $municipality = Municipality::withoutEvents(fn () => Municipality::create([
                'name' => 'Sample municipality '.($index + 1), 'code' => 'SAMPLE-R1-'.($index + 1),
                'province' => $name, 'province_id' => $province->id, 'is_active' => true,
            ]));
            $this->municipalities[] = $municipality;
            $this->createBoundary($municipality, 120.5 + ($index * 0.1), 17.0);
        }
    }

    public function test_default_preview_reports_the_cohort_without_writing_any_rows(): void
    {
        $before = $this->snapshot();
        $result = app(RegionOneSampleData::class)->populate($this->owner, $this->municipalityIds());

        $this->assertSummary($result, 'preview');
        $this->assertSame($before, $this->snapshot());
        $this->assertEmptyOperationalTables();
    }

    public function test_apply_creates_only_the_requested_scoped_synthetic_graph(): void
    {
        $protected = $this->snapshot(['users', 'regions', 'provinces', 'municipalities', 'municipality_boundaries']);
        $result = $this->populate();

        $this->assertSummary($result, 'created');
        $this->assertSame(12, Farmer::count());
        $this->assertSame(36, FarmPlot::count());
        $this->assertSame(12, RiceSeedDistribution::count());
        $this->assertSame(3, RiceDistributionBatch::count());
        $this->assertSame($protected, $this->snapshot(['users', 'regions', 'provinces', 'municipalities', 'municipality_boundaries']));

        $geometry = app(GeoGeometry::class);
        foreach ($this->municipalities as $municipality) {
            $farmers = Farmer::where('municipality_id', $municipality->id)->orderBy('ffrs')->get();
            $this->assertCount(4, $farmers);
            $batch = RiceDistributionBatch::where('municipality_id', $municipality->id)->sole();
            $boundary = MunicipalityBoundary::where('municipality_id', $municipality->id)->sole();
            $this->assertSame($this->owner->id, $batch->created_by);
            $this->assertSame($this->owner->id, $batch->updated_by);
            $this->assertStringStartsWith('SAMPLE-R1-V1-M'.$municipality->id, $batch->reference);
            $this->assertSame('wet', $batch->planting_season);
            $this->assertSame(2026, $batch->planting_year);

            foreach ($farmers as $index => $farmer) {
                $this->assertSame(sprintf('SAMPLE-R1-V1-M%d-F%02d', $municipality->id, $index + 1), $farmer->ffrs);
                $this->assertMatchesRegularExpression('/sample|synthetic|hypothetical/i', $farmer->first_name.' '.$farmer->last_name);
                $this->assertNull($farmer->contact_number);
                $this->assertNotEmpty($farmer->public_map_token);
                $this->assertSame($municipality->name, $farmer->farm_municipality);
                $this->assertSame($municipality->province, $farmer->farm_province);

                $release = $farmer->riceSeedDistributions()->sole();
                $this->assertSame($municipality->id, $release->municipality_id);
                $this->assertSame($batch->id, $release->batch_id);
                $this->assertSame($farmer->ffrs, $release->lot_series);
                $this->assertSame('rice_seed', $release->input_category);
                $this->assertSame('kg', $release->quantity_unit);
                $this->assertSame(RiceSeedDistribution::CONSENT_UNRECORDED, $release->consent_status);
                $this->assertEquals(SeedReleaseQuantity::derivedKilograms($release->seed_bags, $release->seed_bag_kg), (float) $release->kgs_received);
                $this->assertGreaterThan(0, (float) $release->kgs_received);
                foreach (['first_name', 'middle_name', 'last_name', 'ffrs', 'gender', 'farm_location', 'farm_municipality', 'farm_province', 'farm_area_ha'] as $attribute) {
                    $this->assertSame($farmer->getAttribute($attribute), $release->getAttribute($attribute), 'Release snapshot mismatch: '.$attribute);
                }
                $this->assertMatchesRegularExpression('/sample|synthetic|hypothetical|demonstration/i', $release->input_notes);

                $plots = $farmer->farmPlots()->orderBy('name')->get();
                $this->assertCount(3, $plots);
                foreach ($plots as $plot) {
                    $this->assertStringContainsString($farmer->ffrs.'-P', $plot->name);
                    $this->assertGreaterThan(0, $plot->area_ha);
                    $this->assertSame('inside', $geometry->classifyParcel($plot->polygon_json, $boundary->geojson));
                    $computedArea = $geometry->areaHectares($geometry->fromLatLngRing($plot->polygon_json));
                    $this->assertEqualsWithDelta($computedArea, $plot->area_ha, 0.0001);
                }
                $this->assertEqualsWithDelta(round((float) $plots->sum('area_ha'), 2), (float) $farmer->farm_area_ha, 0.01);
            }
        }

        $this->assertGreaterThan(0, AuditLog::where('user_id', $this->owner->id)->count());
        $this->assertStringNotContainsString('public_map_token', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_repeat_application_is_a_complete_no_op_including_timestamps_tokens_and_audit(): void
    {
        $this->populate();
        $before = $this->snapshot();
        $this->travel(2)->minutes();

        $this->assertSummary($this->populate(), 'unchanged');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_import_receipt_is_owner_only_and_contains_no_raw_farmer_data(): void
    {
        $this->populate();
        $receipt = AuditLog::where('module', 'region1_samples')->sole();
        $this->assertSame($this->owner->id, $receipt->user_id);
        $this->assertNull($receipt->province_id);
        $this->assertSame($this->municipalityIds(), $receipt->metadata['municipality_ids']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $receipt->metadata['fingerprint']);
        $this->assertNull(auth()->user());
        $metadata = json_encode($receipt->metadata, JSON_THROW_ON_ERROR);
        foreach (Farmer::all() as $farmer) {
            $this->assertNull($farmer->date_of_birth);
            $this->assertNull($farmer->rsbsa_no);
            $this->assertStringNotContainsString($farmer->public_map_token, $metadata);
            $this->assertStringNotContainsString($farmer->ffrs, $metadata);
        }
    }

    public function test_command_defaults_to_preview_and_requires_explicit_apply(): void
    {
        $before = $this->snapshot();
        $this->artisan('demo:region1', ['--owner' => (string) $this->owner->id, '--municipality' => array_map('strval', $this->municipalityIds())])
            ->assertSuccessful();
        $this->assertSame($before, $this->snapshot());
        $this->artisan('demo:region1', ['--owner' => (string) $this->owner->id, '--municipality' => array_map('strval', $this->municipalityIds()), '--apply' => true])
            ->assertSuccessful();
        $this->assertSame(12, Farmer::count());
    }

    public function test_existing_unrelated_records_are_preserved(): void
    {
        $municipality = $this->municipalities[0];
        $farmer = Farmer::create([
            'municipality_id' => $municipality->id, 'first_name' => 'Existing', 'last_name' => 'Fixture',
            'ffrs' => 'UNRELATED-FARMER', 'farm_location' => 'Unrelated fixture location',
        ]);
        $batch = RiceDistributionBatch::create([
            'municipality_id' => $municipality->id, 'reference' => 'Unrelated fixture batch',
            'planting_season' => 'dry', 'planting_year' => 2025,
        ]);
        $release = RiceSeedDistribution::create([
            'municipality_id' => $municipality->id, 'farmer_id' => $farmer->id, 'batch_id' => $batch->id,
            'seed_variety_claimed' => 'Existing fixture seed', 'kgs_received' => 7,
            'date_received' => '2025-01-02', 'lot_series' => 'UNRELATED-LOT',
        ]);
        $plot = FarmPlot::create([
            'farmer_id' => $farmer->id, 'name' => 'Existing fixture plot',
            'polygon_json' => [['lat' => 17.01, 'lng' => 120.51], ['lat' => 17.01, 'lng' => 120.511], ['lat' => 17.011, 'lng' => 120.51]],
            'area_ha' => 0.1, 'centroid_lat' => 17.0103, 'centroid_lng' => 120.5103, 'color' => '#123456',
        ]);
        $records = [$farmer, $batch, $release, $plot];
        $before = array_map(fn ($record) => $record->fresh()->getAttributes(), $records);
        $this->populate();
        $this->assertSame($before, array_map(fn ($record) => $record->fresh()->getAttributes(), $records));
        $this->assertSame(13, Farmer::count());
        $this->assertSame(37, FarmPlot::count());
        $this->assertSame(13, RiceSeedDistribution::count());
        $this->assertSame(4, RiceDistributionBatch::count());
    }

    public function test_a_marker_collision_does_not_overwrite_or_reassign_an_existing_farmer(): void
    {
        Farmer::create([
            'municipality_id' => $this->municipalities[1]->id,
            'ffrs' => 'SAMPLE-R1-V1-M'.$this->municipalities[0]->id.'-F01',
            'first_name' => 'Existing collision', 'last_name' => 'Preserve',
        ]);
        $this->assertRejectedWithoutWrites();
    }

    public function test_an_incomplete_existing_cohort_is_not_silently_repaired(): void
    {
        $this->populate();
        DB::table('farm_plots')->where('id', FarmPlot::max('id'))->delete();
        $this->assertRejectedWithoutWrites();
    }

    public function test_an_edited_existing_cohort_is_not_overwritten(): void
    {
        $this->populate();
        DB::table('rice_seed_distributions')->where('id', RiceSeedDistribution::min('id'))->update(['kgs_received' => 123]);
        $this->assertRejectedWithoutWrites();
    }

    /** @dataProvider invalidScopeProvider */
    public function test_inactive_or_wrong_region_scopes_fail_without_writes(string $case): void
    {
        match ($case) {
            'municipality' => DB::table('municipalities')->where('id', $this->municipalities[2]->id)->update(['is_active' => false]),
            'province' => DB::table('provinces')->where('id', $this->municipalities[2]->province_id)->update(['is_active' => false]),
            'region' => DB::table('regions')->where('id', $this->region->id)->update(['is_active' => false]),
            'wrong-region' => DB::table('provinces')->where('id', $this->municipalities[2]->province_id)->update(['region_id' => null]),
            'boundary' => DB::table('municipality_boundaries')->where('municipality_id', $this->municipalities[2]->id)->update(['status' => 'archived']),
        };
        $this->assertRejectedWithoutWrites();
    }

    public static function invalidScopeProvider(): array
    {
        return [['municipality'], ['province'], ['region'], ['wrong-region'], ['boundary']];
    }

    public function test_three_municipalities_must_belong_to_three_distinct_provinces(): void
    {
        DB::table('municipalities')->where('id', $this->municipalities[2]->id)
            ->update(['province_id' => $this->municipalities[1]->province_id, 'province' => $this->municipalities[1]->province]);
        $this->assertRejectedWithoutWrites();
    }

    public function test_fewer_than_three_municipalities_is_rejected(): void
    {
        $this->assertRejectedWithoutWrites(array_slice($this->municipalityIds(), 0, 2));
    }

    public function test_duplicate_municipality_identifiers_are_rejected(): void
    {
        $ids = $this->municipalityIds();
        $this->assertRejectedWithoutWrites([$ids[0], $ids[1], $ids[1]]);
    }

    /** @dataProvider invalidOwnerProvider */
    public function test_owner_authority_is_rechecked_from_the_database(string $case): void
    {
        $change = $case === 'inactive' ? ['is_active' => false] : ['role' => User::ROLE_PROVINCIAL_STAFF];
        DB::table('users')->where('id', $this->owner->id)->update($change);
        $this->assertRejectedWithoutWrites();
    }

    public static function invalidOwnerProvider(): array
    {
        return [['inactive'], ['nonowner']];
    }

    public function test_invalid_boundary_geometry_aborts_the_entire_cohort(): void
    {
        DB::table('municipality_boundaries')->where('municipality_id', $this->municipalities[2]->id)
            ->update(['geojson' => json_encode(['type' => 'Polygon', 'coordinates' => []], JSON_THROW_ON_ERROR)]);
        $this->assertRejectedWithoutWrites();
        $this->assertEmptyOperationalTables();
    }

    public function test_missing_audit_table_prevents_any_operational_writes(): void
    {
        Schema::drop('audit_logs');
        $this->assertRejectedWithoutWrites();
        $this->assertEmptyOperationalTables();
    }

    public function test_failed_audit_receipt_rolls_back_the_whole_import(): void
    {
        AuditLog::creating(function (): void {
            throw new RuntimeException('Synthetic fixture audit storage failure.');
        });

        try {
            $this->assertRejectedWithoutWrites();
            $this->assertEmptyOperationalTables();
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_sample_records_remain_isolated_by_the_existing_access_rules(): void
    {
        $this->populate();
        $ownMunicipality = $this->municipalities[0];
        $staff = User::withoutEvents(fn () => User::create([
            'name' => 'Sample scope fixture', 'email' => 'sample-scope@example.test',
            'password' => 'unused-test-placeholder', 'role' => User::ROLE_PROVINCIAL_STAFF,
            'province_id' => $ownMunicipality->province_id, 'is_active' => true,
        ]));
        $visible = app(MunicipalityAccess::class)->scope(Farmer::query(), $staff)->get();
        $this->assertCount(4, $visible);
        $this->assertSame([$ownMunicipality->id], $visible->pluck('municipality_id')->unique()->values()->all());
        $foreignFarmer = Farmer::where('municipality_id', $this->municipalities[1]->id)->firstOrFail();
        $this->assertFalse($staff->can('view', $foreignFarmer));
        $this->assertFalse($staff->can('view', $foreignFarmer->farmPlots()->firstOrFail()));
        $this->assertFalse($staff->can('view', $foreignFarmer->riceSeedDistributions()->sole()));
    }

    private function populate(): array
    {
        return app(RegionOneSampleData::class)->populate($this->owner, $this->municipalityIds(), true);
    }

    private function municipalityIds(): array
    {
        return array_map(fn (Municipality $municipality): int => (int) $municipality->id, $this->municipalities);
    }

    private function assertSummary(array $result, string $status): void
    {
        $this->assertSame($status, $result['status']);
        foreach (['farmers' => 12, 'plots' => 36, 'seed_releases' => 12, 'batches' => 3] as $key => $expected) {
            $this->assertSame($expected, $result[$key], $key);
        }
        $this->assertCount(3, $result['municipalities']);
    }

    private function assertRejectedWithoutWrites(?array $ids = null): void
    {
        $before = $this->snapshot();
        $caught = null;
        try {
            app(RegionOneSampleData::class)->populate($this->owner, $ids ?? $this->municipalityIds(), true);
        } catch (Throwable $exception) {
            $caught = $exception;
        }
        $this->assertNotNull($caught, 'Invalid or conflicting input must be rejected.');
        $this->assertInstanceOf(DomainException::class, $caught);
        $this->assertSame($before, $this->snapshot(), 'A failed import changed persisted data.');
    }

    private function assertEmptyOperationalTables(): void
    {
        foreach (self::OPERATIONAL_TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    private function snapshot(?array $tables = null): array
    {
        $snapshot = [];
        foreach ($tables ?? [...self::OPERATIONAL_TABLES, 'users', 'regions', 'provinces', 'municipalities', 'municipality_boundaries', 'audit_logs'] as $table) {
            if (Schema::hasTable($table)) {
                $snapshot[$table] = hash('sha256', json_encode(DB::table($table)->orderBy('id')->get()->all(), JSON_THROW_ON_ERROR));
            }
        }

        return $snapshot;
    }

    private function createBoundary(Municipality $municipality, float $longitude, float $latitude): void
    {
        $geometry = app(GeoGeometry::class);
        $polygon = $geometry->prepare(['type' => 'Polygon', 'coordinates' => [[
            [$longitude, $latitude], [$longitude + 0.04, $latitude],
            [$longitude + 0.04, $latitude + 0.04], [$longitude, $latitude + 0.04], [$longitude, $latitude],
        ]]]);
        $metadata = $geometry->metadata($polygon);
        MunicipalityBoundary::create([
            'municipality_id' => $municipality->id, 'name' => 'Synthetic fixture boundary',
            'geojson' => $polygon, 'color' => '#15803D', 'status' => 'active',
            'area_ha' => $metadata['area_ha'], 'centroid_lat' => $metadata['centroid_lat'], 'centroid_lng' => $metadata['centroid_lng'],
            'min_lat' => $metadata['min_lat'], 'max_lat' => $metadata['max_lat'],
            'min_lng' => $metadata['min_lng'], 'max_lng' => $metadata['max_lng'], 'vertex_count' => $metadata['vertices'],
            'created_by' => $this->owner->id, 'updated_by' => $this->owner->id,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('region_id')->nullable()->constrained('regions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('province');
            $table->foreignId('province_id')->constrained('provinces');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            foreach (['name', 'email', 'password', 'role'] as $field) {
                $table->string($field);
            }
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities');
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->foreignId('region_id')->nullable()->constrained('regions');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained('municipalities');
            $this->snapshotColumns($table);
            $table->string('rsbsa_no')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('public_map_token', 40)->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farmer_id')->constrained('farmers');
            $table->string('name')->nullable();
            $table->json('polygon_json');
            $table->decimal('area_ha', 15, 4)->nullable();
            $table->decimal('centroid_lat', 10, 7)->nullable();
            $table->decimal('centroid_lng', 11, 7)->nullable();
            $table->string('color', 16)->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_09_17_000100_create_rice_distribution_batches_table.php'))->up();
        Schema::create('rice_seed_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained('municipalities');
            $table->foreignId('farmer_id')->constrained('farmers');
            $table->foreignId('batch_id')->nullable()->constrained('rice_distribution_batches');
            $this->snapshotColumns($table);
            foreach (['input_category', 'quantity_unit', 'seed_variety_claimed', 'lot_series', 'crop_establishment', 'date_of_sowing_label', 'seed_variety_planted', 'seed_class', 'harvest_season', 'representative_name'] as $field) {
                $table->string($field)->nullable();
            }
            $table->text('input_notes')->nullable();
            foreach (['claimed_area_ha', 'claimed_seeds_kg', 'avg_area_harvested_ha', 'kgs_received', 'registered_rice_area_ha', 'seed_bag_kg'] as $field) {
                $table->decimal($field, 10, 2)->nullable();
            }
            foreach (['avg_weight_per_bag_kg', 'total_production_bags', 'harvest_year', 'seed_bags', 'kp_kits_received'] as $field) {
                $table->unsignedInteger($field)->nullable();
            }
            $table->date('date_received')->nullable();
            $table->string('consent_status', 20)->default('unrecorded');
            $table->timestamps();
        });
        (require database_path('migrations/2026_09_03_000100_create_municipality_boundaries_table.php'))->up();
        (require database_path('migrations/2026_09_21_000100_add_fill_opacity_to_municipality_boundaries.php'))->up();
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        Schema::table('audit_logs', fn (Blueprint $table) => $table->foreignId('province_id')->nullable()->constrained('provinces'));
    }

    private function snapshotColumns(Blueprint $table): void
    {
        foreach (['first_name', 'middle_name', 'last_name', 'ext_name', 'ffrs', 'gender', 'contact_number', 'farm_location', 'farm_province', 'farm_municipality', 'ecosystem', 'ecosystem_source'] as $field) {
            $table->string($field)->nullable();
        }
        $table->date('date_of_birth')->nullable();
        $table->decimal('farm_area_ha', 10, 2)->nullable();
        foreach (['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'] as $field) {
            $table->boolean($field)->default(false);
        }
    }
}
