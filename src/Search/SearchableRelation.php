<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lathe\Support\ConfigStore;

/**
 * A LIKE match on a column of a related model: `term%` by default, `%term%` in contains mode.
 * A belongsTo first reads the matching related keys (up to limits.id_list_max) and matches the
 * foreign key, which lets a large table keep its indexes; anything else is an EXISTS subquery.
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
        $relation = $query->getModel()->{$this->relation}();

        if ($relation instanceof BelongsTo) {
            $cap = app(ConfigStore::class)->integer('limits.id_list_max');
            $related = $relation->getRelated();
            $keys = $related->where(function (Builder $related) use ($term) {
                LikeSearch::where($related, new LikeExpression($related->qualifyColumn($this->column)), $term, $this->mode === 'contains', 'and');
            })->limit($cap + 1)->pluck($relation->getOwnerKeyName())->all();

            // Past the cap the key list stops paying off, so the subquery takes over.
            if (count($keys) <= $cap) {
                $query->whereIn($relation->getQualifiedForeignKeyName(), $keys, $boolean);

                return;
            }
        }

        $query->has($this->relation, '>=', 1, $boolean, function (Builder $related) use ($term) {
            /** @var Builder<Model> $related */
            LikeSearch::where($related, new LikeExpression($related->qualifyColumn($this->column)), $term, $this->mode === 'contains', 'and');
        });
    }
}
