<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MARKER = 'application_installation_baselines';

    private const CORE = ['municipalities', 'farmers', 'farm_plots', 'rice_seed_distributions', 'anti_rabies_vaccinations', 'farmers_cooperatives', 'cooperative_farmer', 'backup_files'];

    public function up(): void
    {
        $existing = array_filter(self::CORE, fn (string $table): bool => Schema::hasTable($table));
        // This migration fills the missing historical start of a NEW database.
        // Installed legacy databases are adopted without DDL, rows or defaults
        // changing. A marker exists only where this migration owns the tables,
        // so rollback cannot drop somebody else's imported baseline.
        if (count($existing) === count(self::CORE) && ! Schema::hasTable(self::MARKER)) {
            return;
        }
        if (Schema::hasTable(self::MARKER) || $existing !== []) {
            throw new RuntimeException('The application baseline is partial or already marked. Review its installation history; this migration will not repair or overwrite it.');
        }
        $allowed = ['users', 'migrations', 'password_resets', 'failed_jobs', 'personal_access_tokens'];
        // MySQL's metadata API otherwise lists every schema the DB user can
        // read. Installation ownership is limited to this connection's database.
        $schema = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? DB::connection()->getDatabaseName() : null;
        if (array_diff(Schema::getTableListing(schema: $schema, schemaQualified: false), $allowed) !== [] || DB::table('users')->exists()
            || Schema::hasColumn('users', 'role') || Schema::hasColumn('users', 'municipality_id')) {
            throw new RuntimeException('Fresh application tables require an empty database with only the standard Laravel tables and no users. Existing installations must be reviewed separately.');
        }

        Schema::create(self::MARKER, function (Blueprint $table): void {
            $table->string('name', 80)->primary();
            $table->timestamp('created_at');
        });
        DB::table(self::MARKER)->insert(['name' => 'legacy_application_v1', 'created_at' => now()]);
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('province')->default('Tarlac');
            $table->string('code')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 30)->default('municipal_staff');
            $table->foreignId('municipality_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->index(['role', 'is_active']);
        });
        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('rsbsa_no')->nullable()->unique();
            $table->string('ffrs')->nullable()->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('ext_name', 50)->nullable();
            $table->string('owner_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other', 'Unspecified'])->nullable();
            foreach (['farm_location', 'farm_province', 'farm_municipality'] as $field) {
                $table->string($field)->nullable()->index();
            }
            $table->string('ecosystem')->nullable();
            $table->string('ecosystem_source')->nullable();
            foreach (['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'] as $flag) {
                $table->boolean($flag)->default(false);
            }
            $table->decimal('farm_area_ha', 10, 2)->nullable();
            $table->timestamps();
            $table->index(['last_name', 'first_name']);
        });
        Schema::create('farm_plots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->string('name', 120)->nullable();
            $table->longText('polygon_json');
            $table->decimal('area_ha', 12, 6)->nullable();
            $table->decimal('centroid_lat', 10, 7)->nullable();
            $table->decimal('centroid_lng', 10, 7)->nullable();
            $table->string('color', 16)->nullable()->default('#22c55e');
            $table->timestamps();
            $table->index(['centroid_lat', 'centroid_lng']);
        });
        $this->releases();
        $this->animalServices();
        Schema::create('farmers_cooperatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name')->index();
            $table->string('chairperson')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('cooperative_farmer', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farmers_cooperative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['farmers_cooperative_id', 'farmer_id']);
        });
        Schema::create('backup_files', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 50)->default('local');
            $table->string('folder', 120)->nullable()->index();
            $table->string('original_name')->index();
            $table->string('stored_name');
            $table->string('path', 500);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            $table->char('sha256', 64)->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    private function releases(): void
    {
        Schema::create('rice_seed_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farmer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('seed_variety_claimed', 120)->nullable()->index();
            $table->decimal('claimed_area_ha', 8, 2)->nullable();
            $table->decimal('claimed_seeds_kg', 8, 2)->nullable();
            $table->string('lot_series', 120)->nullable();
            $table->string('crop_establishment', 50)->nullable();
            $table->string('date_of_sowing_label', 120)->nullable();
            $table->integer('avg_weight_per_bag_kg')->nullable();
            $table->integer('total_production_bags')->nullable();
            $table->decimal('avg_area_harvested_ha', 8, 2)->nullable();
            $table->string('seed_variety_planted', 120)->nullable();
            $table->string('seed_class', 80)->nullable();
            $table->decimal('kgs_received', 8, 2)->default(0);
            $table->date('date_received')->nullable()->index();
            foreach (['last_name', 'first_name', 'middle_name'] as $field) {
                $table->string($field)->nullable();
            }
            $table->string('ext_name', 50)->nullable();
            $table->string('ffrs')->nullable()->index();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('farm_location')->nullable()->index();
            $table->string('farm_province')->nullable();
            $table->string('farm_municipality')->nullable()->index();
            $table->decimal('farm_area_ha', 10, 2)->nullable();
            $table->string('ecosystem')->nullable();
            $table->string('ecosystem_source')->nullable();
            foreach (['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'] as $flag) {
                $table->boolean($flag)->default(false);
            }
            $table->timestamps();
        });
    }

    private function animalServices(): void
    {
        Schema::create('anti_rabies_vaccinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('owner_name', 120)->index();
            $table->string('barangay', 120)->index();
            $table->date('birthday')->nullable();
            $table->string('pet_type', 60)->index();
            $table->string('pet_breed', 120)->nullable();
            $table->string('pet_name', 120)->nullable();
            $table->string('pet_color', 80)->nullable();
            $table->string('administered_by', 120)->nullable();
            $table->integer('vaccination_year')->nullable()->index();
            $table->date('vaccination_date')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::MARKER)) {
            return;
        }
        if (DB::table(self::MARKER)->value('name') !== 'legacy_application_v1') {
            throw new RuntimeException('Unknown installation baseline; rollback refused.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['municipality_id']);
            $table->dropIndex(['role', 'is_active']);
            $table->dropColumn(['municipality_id', 'role', 'is_active', 'last_login_at']);
        });
        foreach (array_reverse(self::CORE) as $table) {
            Schema::dropIfExists($table);
        }
        Schema::drop(self::MARKER);
    }
};
