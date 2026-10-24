<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReportDateSql
{
    /** Completed birthdays, matching MySQL TIMESTAMPDIFF's signed whole years. */
    public static function completedYears(string $column): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/D', $column)) {
            throw new InvalidArgumentException('A fixed report date column is required.');
        }
        $column = DB::connection()->getQueryGrammar()->wrap($column);
        if (DB::getDriverName() !== 'sqlite') {
            return "TIMESTAMPDIFF(YEAR, {$column}, CURDATE())";
        }

        $year = "CAST(strftime('%Y', {$column}) AS INTEGER)";
        $todayYear = "CAST(strftime('%Y', 'now') AS INTEGER)";
        $day = "strftime('%m-%d', {$column})";
        $todayDay = "strftime('%m-%d', 'now')";

        return "(CASE WHEN date({$column}) > date('now') THEN -({$year} - {$todayYear} - ({$day} < {$todayDay})) ELSE {$todayYear} - {$year} - ({$todayDay} < {$day}) END)";
    }
}
