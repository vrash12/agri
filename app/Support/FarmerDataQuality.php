<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * What counts as an incomplete farmer record.
 *
 * The dashboard shows a headline count of farmers with no recorded farm location,
 * the province rollup on the same page breaks that down per municipality, and the
 * farmer directory lists the records behind it. Those three were each written
 * separately and each spelled the rule differently — one compared `farm_location`
 * to `'UNKNOWN'` directly, the other two applied `UPPER()` first.
 *
 * Under this database's `utf8mb4_unicode_ci` collation the three agree, so no figure
 * is currently wrong. They would stop agreeing the moment the column moved to a
 * case-sensitive collation, and on a database where `=` is case-sensitive by default
 * the headline would disagree with the table underneath it. A number a municipal
 * officer reports upward should not depend on a collation setting, so the rule lives
 * here once and every caller reaches it through this class.
 *
 * The importer writes the literal string `'UNKNOWN'` when a release arrives with no
 * location, which is why a sentinel has to be matched at all rather than just null
 * and empty.
 */
final class FarmerDataQuality
{
    /**
     * The sentinel the importer writes when no location was supplied.
     */
    public const UNKNOWN_LOCATION = 'UNKNOWN';

    /**
     * Farmers whose farm location was never recorded.
     */
    public static function missingLocation(Builder $query, string $column = 'farm_location'): Builder
    {
        return $query->where(function (Builder $query) use ($column) {
            $query->whereNull($column)
                ->orWhere($column, '')
                ->orWhereRaw('UPPER('.$column.') = ?', [self::UNKNOWN_LOCATION]);
        });
    }

    /**
     * Farmers with no FFRS reference.
     */
    public static function missingFfrs(Builder $query, string $column = 'ffrs'): Builder
    {
        return $query->where(function (Builder $query) use ($column) {
            $query->whereNull($column)->orWhere($column, '');
        });
    }

    /**
     * The same "missing location" rule as a conditional count, for grouped rollups
     * that cannot take a query scope.
     */
    public static function missingLocationCountExpression(string $column = 'farm_location'): string
    {
        return "SUM(CASE WHEN {$column} IS NULL OR {$column} = ''"
            ." OR UPPER({$column}) = '".self::UNKNOWN_LOCATION."' THEN 1 ELSE 0 END)";
    }

    /**
     * The same "missing FFRS" rule as a conditional count.
     */
    public static function missingFfrsCountExpression(string $column = 'ffrs'): string
    {
        return "SUM(CASE WHEN {$column} IS NULL OR {$column} = '' THEN 1 ELSE 0 END)";
    }
}
