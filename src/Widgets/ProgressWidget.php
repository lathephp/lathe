<?php

namespace Lathe\Widgets;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\ProgressResult;

/**
 * A card that shows how far a number has come towards a target.
 */
abstract class ProgressWidget extends Widget
{
    protected ?string $component = 'progress-widget';

    /**
     * Counts the rows $progress narrows the model to, against all rows or the target.
     *
     * @param  Builder<Model>|Model|class-string<Model>  $model
     * @param  callable(Builder<Model>): Builder<Model>  $progress
     */
    protected function count(LatheRequest $request, Builder|Model|string $model, callable $progress, Expression|string|null $column = null, int|float|null $target = null): ProgressResult
    {
        $column ??= '*';
        $done = $progress($this->builder($model));

        return new ProgressResult($done->count($column), $target ?? $this->builder($model)->count($column));
    }

    /**
     * Sums the column over the rows $progress narrows the model to, against all rows or the target.
     *
     * @param  Builder<Model>|Model|class-string<Model>  $model
     * @param  callable(Builder<Model>): Builder<Model>  $progress
     */
    protected function sum(LatheRequest $request, Builder|Model|string $model, callable $progress, Expression|string $column, int|float|null $target = null): ProgressResult
    {
        $done = $progress($this->builder($model));

        return new ProgressResult((float) $done->sum($column), $target ?? (float) $this->builder($model)->sum($column));
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     * @return Builder<Model>
     */
    private function builder(Builder|Model|string $model): Builder
    {
        return AggregateQuery::builderFor($model);
    }
}
