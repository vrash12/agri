<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The one place that turns seed bags into released kilograms.
 *
 * The Rice Seed Distribution Sheet records how many bags were handed out and how
 * much one bag weighs. The released total is not stored a second time: it is
 * written into the existing `kgs_received` column so the module's kilogram
 * totals, charts, CSV export and dashboard keep reading a single source of truth.
 *
 * This class also owns the answer to "which releases count as kilograms", because
 * `kgs_received` is not a kilogram column — it holds a quantity whose unit lives in
 * `quantity_unit`, and nothing in this system converts between units. A total that
 * adds 5,000 fingerlings to 40 kg of seed and prints the result under a heading
 * reading "(kg)" is not a rounding error; it is a false statement on a document an
 * officer signs. AGENTS.md section 5.5 states the rule, and every kilogram total
 * should reach it through here rather than restating the predicate.
 */
final class SeedReleaseQuantity
{
    /**
     * Units whose recorded quantity is already a number of kilograms.
     *
     * Blank and null are included because the column predates the unit column, so
     * legacy rows carry no unit and have always been read as kilograms.
     */
    public const KILOGRAM_UNITS = ['', 'kg'];

    public static function isKilogramUnit(mixed $unit): bool
    {
        if ($unit === null) {
            return true;
        }

        return in_array(is_string($unit) ? trim($unit) : $unit, self::KILOGRAM_UNITS, true);
    }

    /**
     * Restrict a release query to the rows a kilogram total may include.
     */
    public static function onlyKilograms(Builder $query, string $column = 'quantity_unit'): Builder
    {
        return $query->where(function (Builder $query) use ($column) {
            $query->whereNull($column)
                ->orWhere($column, '')
                ->orWhere($column, 'kg');
        });
    }

    /**
     * A SUM that counts only the rows recorded in kilograms.
     *
     * Rows in another unit contribute zero rather than being dropped, so the
     * aggregate still returns a row when every release is in pieces.
     */
    public static function kilogramSumExpression(string $column, string $unitColumn = 'quantity_unit'): string
    {
        return "COALESCE(SUM(CASE WHEN {$unitColumn} IS NULL OR {$unitColumn} = '' OR {$unitColumn} = 'kg'"
            ." THEN {$column} ELSE 0 END), 0)";
    }

    /**
     * Kilograms implied by bags x bag weight, or null when either part is absent
     * or not a usable number. A null result means the recorded total must come
     * from the operator instead.
     */
    public static function derivedKilograms(mixed $bags, mixed $bagKilograms): ?float
    {
        $bagCount = self::numeric($bags);
        $bagWeight = self::numeric($bagKilograms);

        if ($bagCount === null || $bagWeight === null) {
            return null;
        }

        if ($bagCount < 0 || $bagWeight < 0) {
            return null;
        }

        // Two decimals matches the decimal(8,2) column the value is stored in, so
        // the saved figure and the figure shown on the sheet cannot disagree.
        return round($bagCount * $bagWeight, 2);
    }

    private static function numeric(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
