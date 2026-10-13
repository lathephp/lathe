<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * A match on the whole value of one column: `= term`.
 */
class SearchableExact implements SearchableColumn
{
    public function __construct(public readonly string $column)
    {
        //
    }

    public function apply(Builder $query, string $term, string $boolean): void
    {
        $query->where($query->qualifyColumn($this->column), '=', $term, $boolean);
    }
}
