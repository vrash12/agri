<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FarmerRegistrySectionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'assistant.enabled' => false, 'lgu_websites.domain' => '']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('region_id');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->foreignId('province_id');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id');
            $table->string('first_name');
            $table->string('last_name');
        });
        DB::table('regions')->insert([['id' => 1, 'name' => 'Region One'], ['id' => 2, 'name' => 'Region Two']]);
        DB::table('provinces')->insert([['id' => 1, 'name' => 'Province One', 'region_id' => 1], ['id' => 2, 'name' => 'Province Two', 'region_id' => 2]]);
        DB::table('municipalities')->insert([
            ['id' => 1, 'name' => 'Town One', 'province' => 'Province One', 'province_id' => 1],
            ['id' => 2, 'name' => 'Town Two', 'province' => 'Province Two', 'province_id' => 2],
            ['id' => 3, 'name' => 'Empty Town', 'province' => 'Province One', 'province_id' => 1],
        ]);
        DB::table('farmers')->insert([
            ['id' => 1, 'municipality_id' => 1, 'first_name' => 'Local', 'last_name' => 'Grower'],
            ['id' => 2, 'municipality_id' => 2, 'first_name' => 'Foreign', 'last_name' => 'Grower'],
        ]);
        (require database_path('migrations/2026_09_22_000100_create_farmer_registry_source_rows_table.php'))->up();
        $this->source('outside_lgu', 1, ['FIRST NAME' => 'Local', 'MIDDLE NAME' => 'Middle', 'LAST NAME' => 'Grower', 'FARMER ADDRESS 1' => 'Recorded outside address', 'COMMODITY NAME' => 'Rice'], 1);
        $this->source('outside_lgu', 1, ['FIRST NAME' => 'Local', 'LAST NAME' => 'Grower', 'COMMODITY NAME' => 'Corn'], 1);
        $this->source('delisted', 1, ['first_name' => 'Archived', 'last_name' => 'Resident', 'farmer_address_bgy' => 'Original barangay', 'duplicate' => 'duplicate', 'deceased' => 'Deceased', 'inactive' => 'Inactive']);
        $this->source('outside_lgu', 2, ['FIRST NAME' => 'ForeignPrivate', 'LAST NAME' => 'Grower'], 2);
        $this->source('delisted', 2, ['first_name' => 'ForeignArchived', 'last_name' => 'Resident']);
        $this->source('active', 1, ['FIRST NAME' => 'ActiveOnly', 'LAST NAME' => 'Resident'], 1);
    }

    public function test_municipal_sections_are_scoped_read_only_and_keep_repeated_rows(): void
    {
        $before = DB::table('farmer_registry_source_rows')->get()->toJson();
        $this->actingAs($this->user());
        $outside = $this->get('/farmers/registry-sources/outside_lgu?municipality_id=2');
        $outside->assertOk()->assertSee('(2 rows)')->assertSee('Local Middle Grower')->assertSee('Recorded outside address')
            ->assertSee('Rice')->assertSee('Corn')->assertDontSee('ForeignPrivate')->assertDontSee('ActiveOnly')->assertSee('/farmers/1/records')
            ->assertSee('The recorded outside address does not transfer ownership');
        $this->assertStringContainsString('private', $outside->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $outside->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $outside->headers->get('X-Content-Type-Options'));
        $delisted = $this->get('/farmers/registry-sources/delisted');
        $delisted->assertOk()->assertSee('Archived Resident')->assertSee('Duplicate')->assertSee('Deceased')->assertSee('Inactive')
            ->assertSee('Original barangay')->assertDontSee('ForeignArchived')->assertDontSee('View linked farmer profile')
            ->assertDontSee('id-card')->assertSee('do not create or reactivate');
        $this->assertSame($before, DB::table('farmer_registry_source_rows')->get()->toJson());
        $this->assertSame(2, DB::table('farmers')->count());
    }

    public function test_each_permitted_office_role_sees_only_its_existing_scope(): void
    {
        foreach ([
            [User::ROLE_SYSTEM_OWNER, [], 3],
            [User::ROLE_REGIONAL_HEAD, ['region_id' => 1], 2],
            [User::ROLE_SUPER_ADMIN, ['province_id' => 1], 2],
            [User::ROLE_PROVINCIAL_STAFF, ['province_id' => 1], 2],
            [User::ROLE_MUNICIPAL_HEAD, ['municipality_id' => 1], 2],
            [User::ROLE_MUNICIPAL_STAFF, ['municipality_id' => 2], 1],
        ] as [$role, $scope, $expected]) {
            $this->actingAs($this->user($role, $scope));
            $this->get('/farmers/registry-sources/outside_lgu')->assertOk()->assertViewHas('sourceRows', fn ($rows) => $rows->total() === $expected);
        }
    }

    public function test_region_province_and_municipality_filters_preserve_the_section(): void
    {
        $this->actingAs($this->user(User::ROLE_SYSTEM_OWNER, []));
        foreach (['region_id=1', 'province_id=1', 'municipality_id=1'] as $filter) {
            $this->get('/farmers/registry-sources/delisted?'.$filter)->assertOk()->assertSee('Archived Resident')->assertDontSee('ForeignArchived')
                ->assertSee('action="'.url('/farmers/registry-sources/delisted').'"', false);
        }
        $this->actingAs($this->user(User::ROLE_PROVINCIAL_STAFF, ['province_id' => 1]));
        $this->getJson('/farmers/registry-sources/delisted?municipality_id=2')->assertUnprocessable();
        $this->actingAs($this->user(User::ROLE_REGIONAL_HEAD, ['region_id' => 1]));
        $this->getJson('/farmers/registry-sources/outside_lgu?province_id=2')->assertUnprocessable();
    }

    public function test_search_matches_full_names_both_header_formats_and_identifiers_with_scope_first(): void
    {
        $this->actingAs($this->user());
        $this->get('/farmers/registry-sources/outside_lgu?q=local+middle+grower')->assertOk()->assertViewHas('sourceRows', fn ($rows) => $rows->total() === 1);
        $this->get('/farmers/registry-sources/delisted?q=archived+resident')->assertOk()->assertSee('Archived Resident');
        foreach (['FFRS-1-outside_lgu', 'RSBSA-1-outside_lgu', 'PARCEL-1'] as $search) {
            $this->get('/farmers/registry-sources/outside_lgu?q='.urlencode($search))->assertOk()->assertViewHas('sourceRows', fn ($rows) => $rows->total() === 2);
        }
        $this->get('/farmers/registry-sources/outside_lgu?q=ForeignPrivate')->assertOk()->assertSee('No matching source rows.')->assertDontSee('ForeignPrivate Grower');
        $this->get('/farmers/registry-sources/outside_lgu?q=%27+OR+1%3D1')->assertOk()->assertViewHas('sourceRows', fn ($rows) => $rows->total() === 0);
    }

    public function test_counts_do_not_change_with_search_and_pagination_is_bounded(): void
    {
        for ($i = 0; $i < 29; $i++) {
            $this->source('delisted', 1, ['first_name' => 'Extra', 'last_name' => 'Entry']);
        }
        $this->actingAs($this->user());
        $this->get('/farmers/registry-sources/delisted?per_page=10&page=2&q=Extra')->assertOk()
            ->assertViewHas('sourceRows', fn ($rows) => $rows->count() === 10 && $rows->total() === 29 && $rows->currentPage() === 2)
            ->assertViewHas('sourceCounts', fn ($counts) => $counts === ['outside_lgu' => 2, 'delisted' => 30])
            ->assertSee('page=3')->assertSee('q=Extra');
        $this->get('/farmers/registry-sources/delisted?page=999')->assertOk()->assertSee('Return to first page')->assertDontSee('No Delisted source rows retained');
        foreach (['per_page=101', 'per_page=0', 'per_page=11', 'page=-1', 'page=1000001', 'q%5B%5D=x', 'q='.str_repeat('a', 101)] as $bad) {
            $this->getJson('/farmers/registry-sources/delisted?'.$bad)->assertUnprocessable();
        }
    }

    public function test_other_identity_types_and_inactive_offices_cannot_read_source_data(): void
    {
        foreach ([
            $this->user(User::ROLE_PROVINCIAL_VET, ['province_id' => 1]),
            $this->user(User::ROLE_GIS_EVALUATOR, ['evaluation_expires_at' => now()->addDay()]),
            $this->user(User::ROLE_SUPER_ADMIN, ['visitor_mode' => true]),
            $this->user(User::ROLE_MUNICIPAL_STAFF, ['municipality_id' => 1, 'is_active' => false]),
        ] as $user) {
            $this->actingAs($user);
            $this->getJson('/farmers/registry-sources/delisted')->assertForbidden()->assertDontSee('Archived Resident');
        }
        $this->actingAs($this->user());
        DB::table('provinces')->where('id', 1)->update(['is_active' => false]);
        $this->getJson('/farmers/registry-sources/delisted')->assertForbidden();
    }

    public function test_guests_unknown_sections_and_write_methods_have_no_access(): void
    {
        $this->get('/farmers/registry-sources/delisted')->assertRedirect(route('login'));
        $this->actingAs($this->user());
        $this->get('/farmers/registry-sources/active')->assertNotFound();
        $this->post('/farmers/registry-sources/delisted')->assertStatus(405);
    }

    public function test_cross_municipality_and_delisted_farmer_links_never_create_profile_actions(): void
    {
        $this->source('outside_lgu', 1, ['FIRST NAME' => 'Broken', 'LAST NAME' => 'Link'], 2);
        $this->source('delisted', 1, ['first_name' => 'Stale', 'last_name' => 'Link'], 1);
        $this->actingAs($this->user());
        $this->get('/farmers/registry-sources/outside_lgu?q=Broken')->assertOk()->assertSee('Broken Link')->assertDontSee('/farmers/2/records')->assertDontSee('Foreign Grower');
        $this->get('/farmers/registry-sources/delisted?q=Stale')->assertOk()->assertDontSee('View linked farmer profile')->assertDontSee('/farmers/1/records');
    }

    public function test_missing_source_table_and_empty_lgu_have_explicit_states(): void
    {
        $this->actingAs($this->user(User::ROLE_MUNICIPAL_STAFF, ['municipality_id' => 3]));
        $this->get('/farmers/registry-sources/delisted')->assertOk()->assertSee('No Delisted source rows retained for this area.');
        Schema::drop('farmer_registry_source_rows');
        $this->get('/farmers/registry-sources/outside_lgu')->assertOk()->assertSee('Workbook records are unavailable')->assertDontSee('0 retained source rows');
    }

    public function test_original_values_are_escaped_and_zero_and_blank_remain_distinct(): void
    {
        $this->source('delisted', 1, ['first_name' => '<script>alert(1)</script>', 'last_name' => 'Entry', '<b>label</b>' => '<img src=x onerror=alert(1)>', 'zero' => 0, 'empty' => null, 'inactive' => '0']);
        $this->actingAs($this->user());
        $this->get('/farmers/registry-sources/delisted?q=alert')->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('Reason not recorded')->assertSee('<dd>0</dd>', false)->assertSee('Not recorded');
    }

    public function test_search_treats_wildcards_and_escape_characters_as_literal_text(): void
    {
        $this->source('delisted', 1, ['first_name' => 'Literal%_!', 'last_name' => 'Entry']);
        $this->actingAs($this->user());
        foreach (['%', '_', '!'] as $symbol) {
            $this->get('/farmers/registry-sources/delisted?q='.urlencode($symbol))->assertOk()
                ->assertViewHas('sourceRows', fn ($rows) => $rows->total() === 1)->assertSee('Literal%_!');
        }
    }

    private function user(string $role = User::ROLE_MUNICIPAL_STAFF, array $attributes = ['municipality_id' => 1]): User
    {
        $user = new User(['name' => 'Test Office', 'role' => $role] + $attributes + ['is_active' => true]);
        $user->forceFill(['id' => 1] + $attributes);

        return $user;
    }

    private function source(string $status, int $municipalityId, array $payload, ?int $farmerId = null): void
    {
        DB::table('farmer_registry_source_rows')->insert([
            'municipality_id' => $municipalityId, 'farmer_id' => $farmerId,
            'source_file_sha256' => str_repeat('a', 64), 'source_sheet' => strtoupper($status),
            'source_row' => DB::table('farmer_registry_source_rows')->count() + 2, 'record_status' => $status,
            'source_ffrs' => 'FFRS-'.$municipalityId.'-'.$status, 'source_rsbsa_no' => 'RSBSA-'.$municipalityId.'-'.$status,
            'parcel_no' => 'PARCEL-'.$municipalityId, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }
}
