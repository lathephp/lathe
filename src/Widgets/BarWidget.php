<?php

namespace Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\BarResult;

/**
 * A card of bars, one per group.
 */
abstract class BarWidget extends Widget
{
    protected ?string $component = 'bar-widget';

    protected bool $showLabels = false;

    protected bool $insideLabels = false;

    protected ?int $activeIndex = null;

    /**
     * Shows the value on each bar.
     */
    public function showLabels(bool $show = true): static
    {
        $this->showLabels = $show;

        return $this;
    }

    /**
     * Puts the label inside the bar and the value at its end, for horizontal bars.
     */
    public function insideLabels(bool $show = true): static
    {
        $this->insideLabels = $show;

        return $this;
    }

    /**
     * Highlights one bar.
     */
    public function activeIndex(int $index): static
    {
        $this->activeIndex = $index;

        return $this;
    }

    public function resolve(LatheRequest $request): mixed
    {
        $value = parent::resolve($request);

        return $value instanceof BarResult ? $value->payload() : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function descriptor(): array
    {
        return [
            ...parent::descriptor(),
            ...($this->showLabels ? ['showLabels' => true] : []),
            ...($this->insideLabels ? ['insideLabels' => true] : []),
            ...($this->activeIndex === null ? [] : ['activeIndex' => $this->activeIndex]),
        ];
    }

    /**
     * @param  array<string, int|float>  $bars
     */
    protected function result(array $bars): BarResult
    {
        return new BarResult($bars);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function count(LatheRequest $request, Builder|Model|string $model, Expression|string $groupBy, Expression|string|null $column = null): BarResult
    {
        return AggregateQuery::for($model)->count($column)->groupBy($groupBy)->bar();
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sum(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): BarResult
    {
        return AggregateQuery::for($model)->sum($column)->groupBy($groupBy)->bar();
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function average(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): BarResult
    {
        return AggregateQuery::for($model)->average($column)->groupBy($groupBy)->bar();
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function max(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): BarResult
    {
        return AggregateQuery::for($model)->max($column)->groupBy($groupBy)->bar();
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function min(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): BarResult
    {
        return AggregateQuery::for($model)->min($column)->groupBy($groupBy)->bar();
    }
}
