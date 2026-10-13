<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A LIKE match on one column: `term%` by default, `%term%` when declared contains.
 */
class LikeSearch implements SearchableColumn
{
    public function __construct(
        public readonly string $column,
        public readonly bool $contains = false,
    ) {
        //
    }

    public static function prefix(string $column): self
    {
        return new self($column);
    }

    /**
     * Match anywhere in the column. No index helps this on a large table.
     */
    public static function contains(string $column): self
    {
        return new self($column, true);
    }

    public function apply(Builder $query, string $term, string $boolean): void
    {
        static::where($query, new LikeExpression($query->qualifyColumn($this->column)), $term, $this->contains, $boolean);
    }

    /**
     * Add the LIKE clause with `%`, `_` and `\` in the term escaped, so they match themselves.
     *
     * @param  Builder<Model>  $query
     */
    public static function where(Builder $query, LikeExpression $like, string $term, bool $contains, string $boolean): void
    {
        $pattern = ($contains ? '%' : '').addcslashes($term, '%_\\').'%';

        $query->whereRaw($like, [$pattern], $boolean);
    }
}
