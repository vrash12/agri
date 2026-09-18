<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which release a harvest record came from, when it came from one.
 *
 * The Rice Seed Distribution Sheet already has a production monitoring section, and
 * staff already fill it in on paper. Rather than ask them to enter a harvest twice,
 * saving a release with those fields writes the matching harvest record.
 *
 * The link exists so that is a projection rather than a duplicate: editing the same
 * release updates the same harvest row instead of leaving a second one behind, and
 * clearing the production fields removes it. A harvest entered directly, for a
 * commodity no seed sheet covers, simply leaves this null.
 *
 * Additive and reversible, per AGENTS.md section 9.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('harvest_records') || Schema::hasColumn('harvest_records', 'rice_seed_distribution_id')) {
            return;
        }

        Schema::table('harvest_records', function (Blueprint $table) {
            $table->unsignedBigInteger('rice_seed_distribution_id')->nullable()->after('farm_plot_id');

            // One release projects to at most one harvest record. The uniqueness is
            // what makes a re-save an update rather than an accumulation.
            $table->unique('rice_seed_distribution_id', 'harvest_records_release_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('harvest_records') || ! Schema::hasColumn('harvest_records', 'rice_seed_distribution_id')) {
            return;
        }

        Schema::table('harvest_records', function (Blueprint $table) {
            $table->dropUnique('harvest_records_release_unique');
            $table->dropColumn('rice_seed_distribution_id');
        });
    }
};
