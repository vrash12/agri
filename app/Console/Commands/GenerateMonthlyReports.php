<?php

namespace App\Console\Commands;

use App\Modules\Reporting\Application\PeriodReports;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Saves last month's report for every active municipality and province into the
 * in-app archive. Idempotent: a month already archived is left unchanged, so the
 * scheduler or an operator can safely run it again.
 */
final class GenerateMonthlyReports extends Command
{
    protected $signature = 'reports:generate-monthly
        {--month= : Month to archive as YYYY-MM (default: the previous month, office time)}';

    protected $description = 'Archive monthly period reports for each active municipality and province';

    public function handle(PeriodReports $reports): int
    {
        if (! Schema::hasTable('generated_reports')) {
            $this->warn('The generated_reports table is not installed yet; nothing was archived.');

            return self::SUCCESS;
        }

        $option = $this->option('month');
        if (is_string($option) && $option !== '') {
            if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/D', $option, $matches) !== 1) {
                $this->error('Use --month=YYYY-MM.');

                return self::INVALID;
            }
            $month = CarbonImmutable::create((int) $matches[1], (int) $matches[2], 1, 0, 0, 0, LocalTime::timezone());
        } else {
            $month = CarbonImmutable::instance(LocalTime::now())->startOfMonth()->subMonth();
        }

        $current = CarbonImmutable::instance(LocalTime::now())->startOfMonth();
        if ($month->greaterThanOrEqualTo($current)) {
            $this->error('Only a month that has ended can be archived automatically.');

            return self::INVALID;
        }

        $counts = $reports->generateMonthly($month);
        $this->info(sprintf('%s: %d report(s) archived, %d already present.', $month->format('F Y'), $counts['created'], $counts['existing']));

        return self::SUCCESS;
    }
}
