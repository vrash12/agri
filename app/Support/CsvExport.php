<?php

namespace App\Support;

/**
 * Shared formatting for values written into CSV exports.
 *
 * A spreadsheet treats a cell beginning with `=`, `+`, `-` or `@` as a formula, so
 * an exported value a member of staff typed can be executed when a colleague opens
 * the file. Prefixing an apostrophe keeps the text visible and inert.
 *
 * This lived as three byte-identical private copies in the machinery, audit trail
 * and assistance controllers. One copy means a future export cannot quietly ship
 * without the protection, and the rule can be tested in one place.
 */
final class CsvExport
{
    /**
     * Render one value safely for a CSV cell.
     */
    public static function value(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    /**
     * Render a whole row.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    public static function row(array $row): array
    {
        return array_map(static fn (mixed $value): string => self::value($value), $row);
    }
}
