<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One way a search term matches a Resource's rows.
 */
interface SearchableColumn
{
    /**
     * Add this column's match to the query, joined with `and` or `or`.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, string $term, string $boolean): void;
}
