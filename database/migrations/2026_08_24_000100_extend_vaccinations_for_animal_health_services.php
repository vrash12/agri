<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('anti_rabies_vaccinations')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `anti_rabies_vaccinations` MODIFY `pet_type` VARCHAR(60) NOT NULL");
            DB::statement("ALTER TABLE `anti_rabies_vaccinations` MODIFY `birthday` DATE NULL");
            DB::statement("ALTER TABLE `anti_rabies_vaccinations` MODIFY `pet_breed` VARCHAR(120) NULL");
            DB::statement("ALTER TABLE `anti_rabies_vaccinations` MODIFY `pet_name` VARCHAR(120) NULL");
        }

        Schema::table('anti_rabies_vaccinations', function (Blueprint $table) {
            if (! Schema::hasColumn('anti_rabies_vaccinations', 'service_type')) {
                $table->string('service_type', 30)
                    ->default('vaccination')
                    ->after('pet_color')
                    ->index();
            }

            if (! Schema::hasColumn('anti_rabies_vaccinations', 'service_name')) {
                $table->string('service_name', 150)->nullable()->after('service_type');
            }

            if (! Schema::hasColumn('anti_rabies_vaccinations', 'animal_count')) {
                $table->unsignedInteger('animal_count')->default(1)->after('service_name');
            }

            if (! Schema::hasColumn('anti_rabies_vaccinations', 'dosage')) {
                $table->string('dosage', 120)->nullable()->after('animal_count');
            }

            if (! Schema::hasColumn('anti_rabies_vaccinations', 'administration_route')) {
                $table->string('administration_route', 60)->nullable()->after('dosage');
            }

            if (! Schema::hasColumn('anti_rabies_vaccinations', 'diagnosis')) {
                $table->string('diagnosis', 255)->nullable()->after('administration_route');
            }

            if (! Schema::hasColumn('anti_rabies_vaccinations', 'treatment_notes')) {
                $table->text('treatment_notes')->nullable()->after('diagnosis');
            }

            if (! Schema::hasColumn('anti_rabies_vaccinations', 'administered_by')) {
                $table->string('administered_by', 120)->nullable()->after('treatment_notes');
            }

            if (! Schema::hasColumn('anti_rabies_vaccinations', 'next_service_date')) {
                $table->date('next_service_date')->nullable()->after('vaccination_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('anti_rabies_vaccinations')) {
            return;
        }

        $columns = [
            'service_type',
            'service_name',
            'animal_count',
            'dosage',
            'administration_route',
            'diagnosis',
            'treatment_notes',
            'administered_by',
            'next_service_date',
        ];

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn('anti_rabies_vaccinations', $column)
        ));

        if ($existing !== []) {
            Schema::table('anti_rabies_vaccinations', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }

        // pet_type deliberately remains VARCHAR on rollback. Shrinking it back to
        // Dog/Cat would destroy or invalidate legitimate farm-animal records.
    }
};
