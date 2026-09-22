<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DashboardCoverage;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardCoverageTest extends TestCase
{
    private DashboardCoverage $coverage;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('region_id')->nullable();
        });
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('province_id')->nullable();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
        });
        Schema::create('rice_seed_distributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->date('date_received')->nullable();
        });
        Schema::create('municipality_boundaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('status');
        });
        DB::table('provinces')->insert(['id' => 1, 'name' => 'Home', 'is_active' => true]);
        DB::table('municipalities')->insert([
            ['id' => 1, 'name' => 'Covered', 'province_id' => 1, 'is_active' => true],
            ['id' => 2, 'name' => 'Missing', 'province_id' => 1, 'is_active' => true],
            ['id' => 3, 'name' => 'Inactive', 'province_id' => 1, 'is_active' => false],
            ['id' => 4, 'name' => 'Foreign', 'province_id' => 2, 'is_active' => true],
        ]);
        DB::table('provinces')->insert(['id' => 2, 'name' => 'Foreign', 'is_active' => true]);
        DB::table('regions')->insert(['id' => 1, 'name' => 'Home region']);
        DB::table('provinces')->where('id', 1)->update(['region_id' => 1]);
        $this->coverage = new DashboardCoverage(new MunicipalityAccess);
    }

    public function test_assistance_reach_counts_unique_valid_links_and_respects_year_and_scope(): void
    {
        $user = $this->user(User::ROLE_MUNICIPAL_STAFF, 1);
        DB::table('farmers')->insert([['id' => 1, 'municipality_id' => 1], ['id' => 2, 'municipality_id' => 1], ['id' => 3, 'municipality_id' => 2]]);
        DB::table('rice_seed_distributions')->insert([
            ['municipality_id' => 1, 'farmer_id' => 1, 'date_received' => '2026-01-01'],
            ['municipality_id' => 1, 'farmer_id' => 1, 'date_received' => '2026-02-01'],
            ['municipality_id' => 1, 'farmer_id' => 2, 'date_received' => '2025-12-31'],
            ['municipality_id' => 1, 'farmer_id' => 3, 'date_received' => '2026-03-01'],
            ['municipality_id' => 1, 'farmer_id' => null, 'date_received' => '2026-04-01'],
            ['municipality_id' => 1, 'farmer_id' => null, 'date_received' => null],
        ]);

        $stats = $this->coverage->assistance($user, 2026);

        $this->assertSame(2, $stats['registered']);
        $this->assertSame(1, $stats['beneficiaries']);
        $this->assertSame(1, $stats['without_release']);
        $this->assertSame(50.0, $stats['reach_percent']);
        $this->assertSame(4, $stats['releases']);
        $this->assertSame(2, $stats['unlinked_releases']);
        $this->assertSame(1, $stats['undated_releases']);
    }

    public function test_geofence_readiness_counts_one_active_reference_and_isolates_municipalities(): void
    {
        $user = $this->user(User::ROLE_MUNICIPAL_STAFF, 1);
        DB::table('municipality_boundaries')->insert([
            ['municipality_id' => 1, 'status' => 'active'],
            ['municipality_id' => 1, 'status' => 'draft'],
            ['municipality_id' => 2, 'status' => 'active'],
        ]);

        $stats = $this->coverage->geofences($user);

        $this->assertSame(1, $stats['municipalities']);
        $this->assertSame(1, $stats['covered']);
        $this->assertSame(0, $stats['missing']);
        $this->assertSame(0, $stats['ambiguous']);
        $this->assertSame(100.0, $stats['coverage_percent']);
    }

    public function test_province_and_regional_coverage_exclude_foreign_and_inactive_workspaces(): void
    {
        DB::table('municipality_boundaries')->insert([
            ['municipality_id' => 1, 'status' => 'active'],
            ['municipality_id' => 1, 'status' => 'active'],
            ['municipality_id' => 2, 'status' => 'draft'],
            ['municipality_id' => 3, 'status' => 'active'],
            ['municipality_id' => 4, 'status' => 'active'],
        ]);
        DB::table('farmers')->insert([['id' => 1, 'municipality_id' => 1], ['id' => 2, 'municipality_id' => 4]]);
        DB::table('rice_seed_distributions')->insert([
            ['municipality_id' => 1, 'farmer_id' => 1, 'date_received' => '2026-12-31'],
            ['municipality_id' => 4, 'farmer_id' => 2, 'date_received' => '2026-01-01'],
        ]);
        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_REGIONAL_HEAD] as $role) {
            $user = $this->user($role, null);
            $user->forceFill($role === User::ROLE_REGIONAL_HEAD ? ['region_id' => 1] : ['province_id' => 1]);
            $geofences = $this->coverage->geofences($user);
            $this->assertSame(2, $geofences['municipalities']);
            $this->assertSame(1, $geofences['missing']);
            $this->assertSame(1, $geofences['ambiguous']);
            $this->assertSame(0, $geofences['covered']);
            $this->assertSame(1, $this->coverage->assistance($user, 2026)['beneficiaries']);
            $this->assertSame(0, $this->coverage->assistance($user, 2025)['beneficiaries']);
        }
    }

    public function test_empty_denominators_unavailable_module_and_invalid_account_scope_are_explicit(): void
    {
        $user = $this->user(User::ROLE_MUNICIPAL_STAFF, 1);
        $this->assertNull($this->coverage->assistance($user, 2026)['reach_percent']);
        $user->is_active = false;
        $this->assertSame(0, $this->coverage->geofences($user)['municipalities']);
        $this->assertNull($this->coverage->geofences($user)['coverage_percent']);
        Schema::drop('municipality_boundaries');
        $this->assertFalse($this->coverage->geofences($user)['available']);
    }

    public function test_query_count_is_constant_as_municipalities_and_releases_grow(): void
    {
        $user = $this->user(User::ROLE_SUPER_ADMIN, null);
        $user->province_id = 1;
        $counts = [];
        foreach ([1, 30] as $size) {
            for ($i = 1; $i <= $size; $i++) {
                DB::table('municipalities')->updateOrInsert(['id' => 10 + $i], ['name' => 'Added '.$i, 'province_id' => 1, 'is_active' => true]);
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->coverage->assistance($user, 2026);
            $this->coverage->geofences($user);
            $counts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThan(20, $counts[1]);
    }

    private function user(string $role, ?int $municipalityId): User
    {
        return new User([
            'id' => 1, 'name' => 'Test', 'email' => 'test@example.test', 'password' => 'x',
            'role' => $role, 'municipality_id' => $municipalityId, 'province_id' => null,
            'is_active' => true,
        ]);
    }
}
