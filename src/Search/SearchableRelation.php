<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A LIKE match on a column of a related model, as an EXISTS subquery: `term%` by default,
 * `%term%` in contains mode.
 */
class SearchableRelation implements SearchableColumn
{
    /**
     * @param  'prefix'|'contains'  $mode
     */
    public function __construct(
        public readonly string $relation,
        public readonly string $column,
        public readonly string $mode = 'prefix',
    ) {
        //
    }

    public function apply(Builder $query, string $term, string $boolean): void
    {
        $query->has($this->relation, '>=', 1, $boolean, function (Builder $related) use ($term) {
            /** @var Builder<Model> $related */
            LikeSearch::where($related, new LikeExpression($related->qualifyColumn($this->column)), $term, $this->mode === 'contains', 'and');
        });
    }
}
