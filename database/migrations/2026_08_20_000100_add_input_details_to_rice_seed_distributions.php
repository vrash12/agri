<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rice_seed_distributions')) {
            return;
        }

        Schema::table('rice_seed_distributions', function (Blueprint $table) {
            if (! Schema::hasColumn('rice_seed_distributions', 'input_category')) {
                $table->string('input_category', 40)
                    ->default('rice_seed')
                    ->after('farmer_id');
            }

            if (! Schema::hasColumn('rice_seed_distributions', 'quantity_unit')) {
                $table->string('quantity_unit', 20)
                    ->default('kg')
                    ->after('kgs_received');
            }

            if (! Schema::hasColumn('rice_seed_distributions', 'input_notes')) {
                $table->text('input_notes')
                    ->nullable()
                    ->after('lot_series');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rice_seed_distributions')) {
            return;
        }

        $columns = collect([
            'input_category',
            'quantity_unit',
            'input_notes',
        ])->filter(
            fn (string $column) => Schema::hasColumn(
                'rice_seed_distributions',
                $column
            )
        )->all();

        if ($columns !== []) {
            Schema::table('rice_seed_distributions', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
