<?php

namespace Lathe\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * A full text match on one column: MATCH ... AGAINST in boolean mode on MySQL and MariaDB,
 * to_tsvector @@ plainto_tsquery on PostgreSQL. SQLite has no full text, so it matches
 * the term anywhere in the column instead.
 */
class SearchableText implements SearchableColumn
{
    /**
     * @param  array<string, mixed>  $options  passed to whereFullText, such as `language` on PostgreSQL
     */
    public function __construct(
        public readonly string $column,
        public readonly array $options = [],
    ) {
        //
    }

    public function apply(Builder $query, string $term, string $boolean): void
    {
        $column = $query->qualifyColumn($this->column);

        if ($query->getModel()->getConnection()->getDriverName() === 'sqlite') {
            LikeSearch::where($query, new LikeExpression($column), $term, true, $boolean);

            return;
        }

        // PostgreSQL ignores the boolean mode and builds plainto_tsquery.
        $query->whereFullText($column, $term, $this->options + ['mode' => 'boolean'], $boolean);
    }
}
