<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * A LIKE match on a JSON path such as `meta->color`: `term%` by default, `%term%` in contains mode.
 */
class SearchableJson implements SearchableColumn
{
    /**
     * @param  'prefix'|'contains'  $mode
     */
    public function __construct(
        public readonly string $path,
        public readonly string $mode = 'prefix',
    ) {
        //
    }

    public function apply(Builder $query, string $term, string $boolean): void
    {
        LikeSearch::where($query, new LikeExpression($query->qualifyColumn($this->path)), $term, $this->mode === 'contains', $boolean);
    }
}
