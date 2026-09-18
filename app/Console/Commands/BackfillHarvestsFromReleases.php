<?php

namespace App\Console\Commands;

use App\Models\RiceSeedDistribution;
use App\Support\HarvestFromRelease;
use Illuminate\Console\Command;

/**
 * Projects production already written on existing distribution sheets.
 *
 * Saving a release now writes its harvest record, but releases recorded before that
 * wiring existed carry production figures nobody will re-save. Without this they stay
 * invisible to the production trend, which would make the chart look emptier than the
 * office's own records are.
 *
 * Safe to run more than once: each release owns at most one harvest record, so a
 * second pass updates what the first pass wrote instead of adding to it.
 */
class BackfillHarvestsFromReleases extends Command
{
    protected $signature = 'harvests:backfill-from-releases {--dry-run : Report what would be projected without writing anything}';

    protected $description = 'Project the production section of existing assistance releases into harvest records';

    public function handle(HarvestFromRelease $projection): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $eligible = RiceSeedDistribution::query()
            ->whereNotNull('harvest_year')
            ->whereNotNull('total_production_bags');

        $total = (clone $eligible)->count();

        if ($total === 0) {
            $this->info('No release carries both a harvest year and a bag count, so there is nothing to project.');

            return self::SUCCESS;
        }

        $this->line($dryRun
            ? "{$total} release(s) report production and would be projected."
            : "Projecting {$total} release(s).");

        $created = 0;
        $updated = 0;

        // Chunked by id so a large register does not have to fit in memory, and so the
        // ordering cannot shift underneath the walk while records are being written.
        $eligible->orderBy('id')->chunkById(200, function ($releases) use ($projection, $dryRun, &$created, &$updated) {
            foreach ($releases as $release) {
                $existed = $release->harvestRecord()->exists();

                if (! $dryRun) {
                    $projection->sync($release);
                }

                $existed ? $updated++ : $created++;
            }
        });

        $this->info($dryRun
            ? "Would create {$created} and refresh {$updated} harvest record(s). Nothing was written."
            : "Created {$created} and refreshed {$updated} harvest record(s).");

        return self::SUCCESS;
    }
}
