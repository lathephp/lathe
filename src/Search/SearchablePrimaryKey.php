<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * The key match every searchable Resource gets: an integer key equals a digit-only term,
 * any other key matches by prefix.
 */
class SearchablePrimaryKey implements SearchableColumn
{
    public function __construct(public readonly int $maxSize = \PHP_INT_MAX)
    {
        //
    }

    public function apply(Builder $query, string $term, string $boolean): void
    {
        $model = $query->getModel();
        $key = $model->getQualifiedKeyName();

        if ($model->getKeyType() === 'int') {
            if ($this->fits($term)) {
                $query->where($key, '=', (int) $term, $boolean);
            }

            return;
        }

        LikeSearch::where($query, new LikeExpression($key, asText: true), $term, false, $boolean);
    }

    /**
     * Whether the term is a whole number no larger than the max size. The digits are compared
     * as strings, because a cast to int clamps a longer number to PHP_INT_MAX.
     */
    protected function fits(string $term): bool
    {
        if (! ctype_digit($term)) {
            return false;
        }

        $digits = ltrim($term, '0') ?: '0';
        $max = (string) $this->maxSize;

        return strlen($digits) < strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) <= 0);
    }
}
