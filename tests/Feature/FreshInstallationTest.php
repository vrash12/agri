<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshInstallationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_all_repository_migrations_install_without_a_database_dump_or_seeded_accounts(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        foreach (['municipalities', 'farmers', 'farm_plots', 'rice_seed_distributions', 'anti_rabies_vaccinations',
            'farmers_cooperatives', 'cooperative_farmer', 'backup_files', 'users', 'provinces', 'regions',
            'harvest_records', 'farmer_portal_accounts', 'farmer_registry_source_rows', 'municipality_profiles',
            'municipality_websites', 'parcel_field_assessments'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
            $this->assertSame(0, DB::table($table)->count(), 'Fresh installs must not invent accounts, offices or operational data: '.$table);
        }
        $this->assertTrue(Schema::hasColumns('users', ['role', 'is_active', 'municipality_id', 'province_id', 'region_id', 'visitor_mode', 'evaluation_expires_at']));
        $this->assertTrue(Schema::hasColumns('farmers', ['profile_photo_path', 'public_map_token', 'ffrs', 'rsbsa_no']));
        $this->assertTrue(Schema::hasColumns('rice_seed_distributions', ['input_category', 'quantity_unit', 'batch_id', 'consent_status', 'harvest_year']));
        $before = DB::table('migrations')->orderBy('id')->get()->toJson();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame($before, DB::table('migrations')->orderBy('id')->get()->toJson());
    }

    public function test_complete_fresh_schema_rolls_back_and_can_be_installed_again(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();
        $this->assertEqualsCanonicalizing(['migrations'], Schema::getTableListing(schemaQualified: false));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('parcel_field_assessments'));
        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_imported_legacy_tables_are_never_modified_or_owned_by_the_new_baseline(): void
    {
        foreach ($this->core() as $table) {
            Schema::create($table, fn (Blueprint $blueprint) => $blueprint->id());
            DB::table($table)->insert(['id' => 42]);
        }
        $migration = $this->baseline();
        $migration->up();
        $migration->down();
        $this->assertFalse(Schema::hasTable('application_installation_baselines'));
        foreach ($this->core() as $table) {
            $this->assertSame([42], DB::table($table)->pluck('id')->all());
            $this->assertSame(['id'], Schema::getColumnListing($table));
        }
    }

    public function test_partial_legacy_databases_fail_before_creating_or_changing_any_table(): void
    {
        Schema::create('farmers', fn (Blueprint $table) => $table->id());
        DB::table('farmers')->insert(['id' => 9]);
        try {
            $this->baseline()->up();
            $this->fail('Partial legacy installation must require review.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('partial', $exception->getMessage());
        }
        $this->assertSame(['farmers'], Schema::getTableListing(schemaQualified: false));
        $this->assertSame([9], DB::table('farmers')->pluck('id')->all());
    }

    public function test_existing_users_are_not_silently_given_new_roles_or_scope(): void
    {
        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        DB::table('users')->insert(['name' => 'Existing', 'email' => 'existing@example.test', 'password' => 'existing-hash']);
        try {
            $this->baseline()->up();
            $this->fail('An existing account needs explicit migration review.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('no users', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertSame(1, DB::table('users')->count());
        $this->assertFalse(Schema::hasTable('municipalities'));
    }

    public function test_unrelated_existing_schema_is_not_treated_as_a_blank_installation(): void
    {
        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        Schema::create('other_application', fn (Blueprint $table) => $table->id());
        $this->expectException(\RuntimeException::class);
        $this->baseline()->up();
    }

    public function test_first_owner_bootstrap_is_audited_and_cannot_be_repeated(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $password = 'Synthetic-fixture-only-654321';
        $this->artisan('system:bootstrap-owner', ['--name' => 'Test Owner', '--email' => 'OWNER@example.test'])
            ->expectsQuestion('Password (15–72 characters; at most 72 bytes)', $password)
            ->expectsQuestion('Confirm password', $password)->assertSuccessful();
        $owner = User::sole();
        $this->assertSame('owner@example.test', $owner->email);
        $this->assertSame(User::ROLE_SYSTEM_OWNER, $owner->role);
        $this->assertTrue($owner->is_active);
        $this->assertNull($owner->municipality_id);
        $this->assertNull($owner->province_id);
        $this->assertNull($owner->region_id);
        $this->assertTrue(Hash::check($password, $owner->password));
        $receipt = DB::table('audit_logs')->where('event', 'installation_owner_created')->sole();
        $this->assertStringNotContainsString($password, json_encode($receipt));
        $this->assertTrue(auth()->attempt(['email' => $owner->email, 'password' => $password]));
        $before = $owner->getRawOriginal();
        $this->artisan('system:bootstrap-owner')->assertFailed();
        $this->assertSame(1, User::count());
        $this->assertSame($before, $owner->fresh()->getRawOriginal());
    }

    public function test_bootstrap_refuses_noninteractive_or_invalid_passwords_without_creating_accounts(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->artisan('system:bootstrap-owner', ['--no-interaction' => true])->assertFailed();
        foreach ([['short', 'short'], ['Long-enough-password-123', 'Different-password-123'], [str_repeat('é', 40), str_repeat('é', 40)]] as [$password, $confirmation]) {
            $this->artisan('system:bootstrap-owner', ['--name' => 'Test Owner', '--email' => 'owner@example.test'])
                ->expectsQuestion('Password (15–72 characters; at most 72 bytes)', $password)
                ->expectsQuestion('Confirm password', $confirmation)->assertFailed();
        }
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_bootstrap_requires_a_known_fresh_installation_marker(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        DB::table('application_installation_baselines')->update(['name' => 'unknown']);
        $this->artisan('system:bootstrap-owner')->assertFailed();
        $this->assertSame(0, User::count());
        Schema::drop('application_installation_baselines');
        $this->artisan('system:bootstrap-owner')->assertFailed();
        $this->assertSame(0, User::count());
    }

    private function baseline(): Migration
    {
        return require database_path('migrations/2014_10_12_000100_create_legacy_application_tables.php');
    }

    private function core(): array
    {
        return ['municipalities', 'farmers', 'farm_plots', 'rice_seed_distributions', 'anti_rabies_vaccinations', 'farmers_cooperatives', 'cooperative_farmer', 'backup_files'];
    }
}
