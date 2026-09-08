<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProvinceSupervisionMigrationTest extends TestCase
{
    public function test_migration_backfills_only_known_ownership_and_can_be_reversed_without_removing_records(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        DB::table('municipalities')->insert([
            ['id' => 1, 'name' => 'Anao', 'province' => 'Tarlac'],
            ['id' => 2, 'name' => 'Atok', 'province' => 'Benguet'],
            ['id' => 3, 'name' => 'Unknown', 'province' => null],
        ]);
        DB::table('users')->insert(['id' => 1, 'role' => 'super_admin']);
        DB::table('audit_logs')->insert([['id' => 1, 'municipality_id' => 1], ['id' => 2, 'municipality_id' => null], ['id' => 3, 'municipality_id' => 900]]);
        $migration = require database_path('migrations/2026_09_08_000100_add_province_supervision.php');
        $migration->up();
        $this->assertSame(2, DB::table('provinces')->count());
        $tarlacId = DB::table('provinces')->where('name', 'Tarlac')->value('id');
        $this->assertSame($tarlacId, DB::table('municipalities')->where('id', 1)->value('province_id'));
        $this->assertSame($tarlacId, DB::table('audit_logs')->where('id', 1)->value('province_id'));
        $this->assertSame(2, DB::table('audit_logs')->whereNull('province_id')->count());
        $this->assertNull(DB::table('users')->where('id', 1)->value('province_id'));
        $this->assertSame('super_admin', DB::table('users')->where('id', 1)->value('role'));
        $migration->down();
        $this->assertFalse(Schema::hasTable('provinces'));
        $this->assertFalse(Schema::hasColumn('users', 'province_id'));
        $this->assertSame(3, DB::table('municipalities')->count());
        $this->assertSame(3, DB::table('audit_logs')->count());
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame('Tarlac', DB::table('municipalities')->where('id', 1)->value('province'));
    }
}
