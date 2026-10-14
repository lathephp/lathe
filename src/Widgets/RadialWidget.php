<?php

namespace Lathe\Widgets;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\RadialResult;

/**
 * A card that draws a value against a target as a gauge or as rings.
 */
abstract class RadialWidget extends Widget
{
    protected ?string $component = 'radial-widget';

    protected string $height = 'double';

    public function resolve(LatheRequest $request): mixed
    {
        $value = parent::resolve($request);

        return $value instanceof RadialResult ? $value->jsonSerialize() : $value;
    }

    protected function result(int|float $value, int|float $target = 100): RadialResult
    {
        return new RadialResult($value, $target);
    }

    /**
     * The rows that pass $progress against all rows; a target replaces the row total.
     *
     * @param  Builder<Model>|Model|class-string<Model>  $model
     * @param  callable(Builder<Model>): mixed  $progress  narrows the rows to the finished ones
     */
    protected function count(LatheRequest $request, Builder|Model|string $model, callable $progress, Expression|string|null $column = null, int|float|null $target = null): RadialResult
    {
        return new RadialResult(
            $this->number(AggregateQuery::for($model)->count($column)->query($progress)),
            $target ?? $this->number(AggregateQuery::for($model)->count($column)),
        );
    }

    /**
     * The column sum of the rows that pass $progress against the sum of all rows; a target replaces the total.
     *
     * @param  Builder<Model>|Model|class-string<Model>  $model
     * @param  callable(Builder<Model>): mixed  $progress  narrows the rows to the finished ones
     */
    protected function sum(LatheRequest $request, Builder|Model|string $model, callable $progress, Expression|string $column, int|float|null $target = null): RadialResult
    {
        return new RadialResult(
            $this->number(AggregateQuery::for($model)->sum($column)->query($progress)),
            $target ?? $this->number(AggregateQuery::for($model)->sum($column)),
        );
    }

    /**
     * The one number of a query with no grouping and no date range.
     */
    protected function number(AggregateQuery $query): int|float
    {
        $read = Closure::bind(static fn (AggregateQuery $query) => $query->aggregate($query->scoped()), null, AggregateQuery::class);

        return $read($query) ?? 0;
    }
}
