<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rice Seed Distribution Sheet batches.
 *
 * A batch only groups existing assistance releases into one printable sheet: it
 * carries the programme reference and the planting/harvest season used for the
 * sheet headings. It deliberately stores no released quantity of its own, so the
 * release rows stay the single source of truth for every total.
 *
 * The repository has no complete migration history (AGENTS.md section 9), so this
 * migration only creates a new table and never touches the imported legacy tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rice_distribution_batches')) {
            return;
        }

        Schema::create('rice_distribution_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('reference', 120);
            $table->string('planting_season', 20);
            $table->unsignedSmallInteger('planting_year');
            $table->string('harvest_season', 20)->nullable();
            $table->unsignedSmallInteger('harvest_year')->nullable();
            $table->decimal('default_seed_bag_kg', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('municipality_id', 'rice_distribution_batch_municipality_index');
            $table->index(
                ['municipality_id', 'planting_year', 'planting_season'],
                'rice_distribution_batch_season_index'
            );

            $table->foreign('municipality_id', 'rice_distribution_batch_municipality_fk')
                ->references('id')
                ->on('municipalities')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('created_by', 'rice_distribution_batch_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'rice_distribution_batch_updated_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // The releases' batch_id foreign key is removed by its own migration, which
        // runs down first, so the table can be dropped without orphaning releases.
        Schema::dropIfExists('rice_distribution_batches');
    }
};
