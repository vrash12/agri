<?php

namespace App\Modules\Farmers\Application;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/** A bounded, literal search appended to an already authorized farmer query. */
final class FarmerIdentitySearch
{
    private const NAMES = ['first_name', 'middle_name', 'last_name', 'ext_name'];

    /** @param array<int, string> $references Fixed caller-owned columns, never request input. */
    public static function apply(Builder $query, string $term, array $references): void
    {
        if (array_diff($references, ['ffrs', 'rsbsa_no', 'farm_location']) !== []) {
            throw new InvalidArgumentException('An approved farmer search column is required.');
        }
        $term = preg_replace('/\s+/u', ' ', trim($term));
        $tokens = $term === null ? [] : preg_split('/[\s,]+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
        if ($term === null || $term === '' || mb_strlen($term) > 200 || count($tokens) > 8) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $match) use ($term, $tokens, $references) {
            foreach ([...self::NAMES, ...$references] as $column) {
                self::orLiteral($match, $column, $term);
            }
            // Every name part must match this same farmer. Reordering or omitting
            // a middle name is allowed; an unknown name part cannot broaden results.
            if (count($tokens) > 1) {
                $match->orWhere(function (Builder $name) use ($tokens) {
                    foreach ($tokens as $token) {
                        $name->where(function (Builder $part) use ($token) {
                            foreach (self::NAMES as $column) {
                                self::orLiteral($part, $column, $token);
                            }
                        });
                    }
                });
            }
            if (($id = FarmerIdentifier::parse($term)) !== null) {
                $match->orWhere('farmers.id', $id);
            }
        });
    }

    private static function orLiteral(Builder $query, string $column, string $value): void
    {
        $column = $query->getQuery()->getGrammar()->wrap('farmers.'.$column);
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
        $query->orWhereRaw("{$column} LIKE ? ESCAPE '!'", [$like]);
    }
}
