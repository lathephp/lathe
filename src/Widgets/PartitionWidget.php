<?php

namespace Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\PartitionResult;

/**
 * A card of segments, one per group, as a donut or as proportion bars.
 */
abstract class PartitionWidget extends Widget
{
    protected ?string $component = 'partition-widget';

    protected bool $asBar = false;

    protected bool $showLabels = false;

    /**
     * Draws horizontal proportion bars instead of a donut.
     */
    public function asBar(): static
    {
        $this->asBar = true;

        return $this;
    }

    /**
     * Draws labels outside the donut segments.
     */
    public function showLabels(bool $show = true): static
    {
        $this->showLabels = $show;

        return $this;
    }

    public function resolve(LatheRequest $request): mixed
    {
        $value = parent::resolve($request);

        return $value instanceof PartitionResult ? $value->payload() : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function descriptor(): array
    {
        return [
            ...parent::descriptor(),
            ...($this->asBar ? ['asBar' => true] : []),
            ...($this->showLabels ? ['showLabels' => true] : []),
        ];
    }

    /**
     * @param  array<string, int|float>  $segments
     */
    protected function result(array $segments): PartitionResult
    {
        return new PartitionResult($segments);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function count(LatheRequest $request, Builder|Model|string $model, Expression|string $groupBy, Expression|string|null $column = null): PartitionResult
    {
        return AggregateQuery::for($model)->count($column)->groupBy($groupBy)->partition();
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sum(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): PartitionResult
    {
        return AggregateQuery::for($model)->sum($column)->groupBy($groupBy)->partition();
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function average(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): PartitionResult
    {
        return AggregateQuery::for($model)->average($column)->groupBy($groupBy)->partition();
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function max(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): PartitionResult
    {
        return AggregateQuery::for($model)->max($column)->groupBy($groupBy)->partition();
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function min(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): PartitionResult
    {
        return AggregateQuery::for($model)->min($column)->groupBy($groupBy)->partition();
    }
}
