<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Search\LikeExpression;
use Lathe\Search\LikeSearch;

/**
 * The options of a select read from one column of a query, which the associatable endpoint
 * searches as the user types. The column's value is both the option's value and its label.
 */
class RemoteOptions
{
    /**
     * @param  Closure  $query  returns the Builder of the rows to offer
     */
    public function __construct(
        public readonly Closure $query,
        public readonly string $column,
        public readonly ?int $inlineUpTo,
    ) {
        //
    }

    /**
     * Every value when the source holds no more than inlineUpTo rows, null when the client
     * must search it. Without inlineUpTo this runs no query.
     *
     * @param  Builder<Model>  $query
     * @return list<string>|null
     */
    public function inline(Builder $query): ?array
    {
        if ($this->inlineUpTo === null || $query->clone()->count() > $this->inlineUpTo) {
            return null;
        }

        return $this->values($query->orderBy($this->column)->pluck($this->column)->all());
    }

    /**
     * One page of the values that contain the term, and whether more match.
     *
     * @param  Builder<Model>  $query
     * @return array{items: list<array{value: string, display: string}>, has_more: bool}
     */
    public function search(Builder $query, string $term, int $limit): array
    {
        if ($term !== '') {
            LikeSearch::where($query, new LikeExpression($query->qualifyColumn($this->column)), $term, true, 'and');
        }

        $values = $this->values($query->orderBy($this->column)->limit($limit + 1)->pluck($this->column)->all());

        return ['items' => $this->items(array_slice($values, 0, $limit)), 'has_more' => count($values) > $limit];
    }

    /**
     * The one saved value, or null when no row holds it.
     *
     * @param  Builder<Model>  $query
     * @return list<array{value: string, display: string}>|null
     */
    public function find(Builder $query, string $value): ?array
    {
        $values = $this->values($query->where($this->column, $value)->limit(1)->pluck($this->column)->all());

        return $values === [] ? null : $this->items($values);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private function values(array $values): array
    {
        return array_values(array_map(fn (mixed $value) => is_scalar($value) ? (string) $value : '', $values));
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, display: string}>
     */
    private function items(array $values): array
    {
        return array_map(fn (string $value) => ['value' => $value, 'display' => $value], $values);
    }
}
