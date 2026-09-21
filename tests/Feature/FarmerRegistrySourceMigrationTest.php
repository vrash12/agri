<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FarmerRegistrySourceMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
        });
    }

    public function test_registry_source_rows_migrate_and_roll_back(): void
    {
        $migration = require database_path(
            'migrations/2026_09_22_000100_create_farmer_registry_source_rows_table.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumns('farmer_registry_source_rows', [
            'municipality_id',
            'farmer_id',
            'source_file_sha256',
            'source_sheet',
            'source_row',
            'record_status',
            'source_ffrs',
            'source_rsbsa_no',
            'parcel_no',
            'payload',
        ]));

        DB::table('municipalities')->insert(['id' => 12]);
        DB::table('farmers')->insert(['id' => 42]);
        DB::table('farmer_registry_source_rows')->insert([
            'municipality_id' => 12,
            'farmer_id' => 42,
            'source_file_sha256' => str_repeat('a', 64),
            'source_sheet' => 'PARCEL LISTING',
            'source_row' => 2,
            'record_status' => 'active',
            'source_ffrs' => 'FFRS-42',
            'source_rsbsa_no' => 'RSBSA-42',
            'parcel_no' => 'PARCEL-42',
            'payload' => json_encode(['COMMODITY NAME' => 'Rice/Palay'], JSON_THROW_ON_ERROR),
        ]);
        $this->assertSame(1, DB::table('farmer_registry_source_rows')->count());

        $migration->down();

        $this->assertFalse(Schema::hasTable('farmer_registry_source_rows'));
    }
}
