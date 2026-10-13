<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A LIKE match on a column of a morph-to parent, as an EXISTS subquery per morph type:
 * `term%` by default, `%term%` in contains mode.
 */
class SearchableMorphToRelation implements SearchableColumn
{
    /**
     * @param  list<class-string<Model>>  $types  the morph types to search; [] searches every type in the table
     * @param  'prefix'|'contains'  $mode
     */
    public function __construct(
        public readonly string $relation,
        public readonly string $column,
        public readonly array $types = [],
        public readonly string $mode = 'prefix',
    ) {
        //
    }

    public function apply(Builder $query, string $term, string $boolean): void
    {
        // With ['*'] Eloquent first reads the distinct morph types stored in the table.
        $query->hasMorph($this->relation, $this->types ?: ['*'], '>=', 1, $boolean, function (Builder $related) use ($term) {
            /** @var Builder<Model> $related */
            LikeSearch::where($related, new LikeExpression($related->qualifyColumn($this->column)), $term, $this->mode === 'contains', 'and');
        });
    }
}
