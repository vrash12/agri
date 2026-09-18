<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rice Seed Distribution Sheet columns on the existing release table.
 *
 * Every column is additive and nullable (or carries a safe default) because the
 * repository has no complete migration history for a clean database (AGENTS.md
 * section 9) and the table already holds imported legacy releases. Existing rows
 * must stay fully usable with `batch_id` null, so nothing is backfilled, renamed
 * or repurposed here.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $columns = [
        'batch_id',
        'registered_rice_area_ha',
        'seed_bags',
        'seed_bag_kg',
        'harvest_season',
        'harvest_year',
        'consent_status',
        'kp_kits_received',
        'representative_name',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('rice_seed_distributions')) {
            return;
        }

        Schema::table('rice_seed_distributions', function (Blueprint $table) {
            if (! Schema::hasColumn('rice_seed_distributions', 'batch_id')) {
                $table->unsignedBigInteger('batch_id')
                    ->nullable()
                    ->after('municipality_id');
                $table->index('batch_id', 'rice_seed_distribution_batch_index');
            }

            // Total farm area already lives in farm_area_ha; the registered rice
            // area is a separate declared figure and must not overwrite it.
            if (! Schema::hasColumn('rice_seed_distributions', 'registered_rice_area_ha')) {
                $table->decimal('registered_rice_area_ha', 8, 2)
                    ->nullable()
                    ->after('farm_area_ha');
            }

            if (! Schema::hasColumn('rice_seed_distributions', 'seed_bags')) {
                $table->unsignedInteger('seed_bags')
                    ->nullable()
                    ->after('kgs_received');
            }

            // Seed bag weight, distinct from the harvest bag weight already stored
            // in avg_weight_per_bag_kg.
            if (! Schema::hasColumn('rice_seed_distributions', 'seed_bag_kg')) {
                $table->decimal('seed_bag_kg', 8, 2)
                    ->nullable()
                    ->after('seed_bags');
            }

            if (! Schema::hasColumn('rice_seed_distributions', 'harvest_season')) {
                $table->string('harvest_season', 20)
                    ->nullable()
                    ->after('avg_area_harvested_ha');
            }

            if (! Schema::hasColumn('rice_seed_distributions', 'harvest_year')) {
                $table->unsignedSmallInteger('harvest_year')
                    ->nullable()
                    ->after('harvest_season');
            }

            // An unanswered privacy question stays unanswered: legacy rows take the
            // 'unrecorded' default rather than being read as a refusal or a consent.
            if (! Schema::hasColumn('rice_seed_distributions', 'consent_status')) {
                $table->string('consent_status', 20)
                    ->default('unrecorded')
                    ->after('date_received');
            }

            if (! Schema::hasColumn('rice_seed_distributions', 'kp_kits_received')) {
                $table->unsignedInteger('kp_kits_received')
                    ->nullable()
                    ->after('consent_status');
            }

            if (! Schema::hasColumn('rice_seed_distributions', 'representative_name')) {
                $table->string('representative_name', 150)
                    ->nullable()
                    ->after('kp_kits_received');
            }
        });

        if (
            Schema::hasTable('rice_distribution_batches')
            && Schema::hasColumn('rice_seed_distributions', 'batch_id')
            && ! $this->hasBatchForeignKey()
        ) {
            Schema::table('rice_seed_distributions', function (Blueprint $table) {
                // Removing a batch must never destroy the releases it grouped, so
                // the release simply becomes unbatched again.
                $table->foreign('batch_id', 'rice_seed_distribution_batch_fk')
                    ->references('id')
                    ->on('rice_distribution_batches')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('rice_seed_distributions')) {
            return;
        }

        if ($this->hasBatchForeignKey()) {
            Schema::table('rice_seed_distributions', function (Blueprint $table) {
                $table->dropForeign('rice_seed_distribution_batch_fk');
            });
        }

        $columns = collect($this->columns)
            ->filter(fn (string $column) => Schema::hasColumn('rice_seed_distributions', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('rice_seed_distributions', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * Foreign-key introspection is driver specific, and this migration must stay
     * re-runnable against the imported legacy schema without doctrine/dbal.
     */
    private function hasBatchForeignKey(): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return false;
        }

        return $connection->selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            ['rice_seed_distributions', 'rice_seed_distribution_batch_fk', 'FOREIGN KEY']
        ) !== null;
    }
};
