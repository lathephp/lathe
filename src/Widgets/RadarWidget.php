<?php

namespace Lathe\Widgets;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Widgets\Results\RadarResult;

/**
 * A card of datasets drawn over a ring of axes.
 */
abstract class RadarWidget extends Widget
{
    protected ?string $component = 'radar-widget';

    protected string $height = 'double';

    public function resolve(LatheRequest $request): mixed
    {
        $value = parent::resolve($request);

        return $value instanceof RadarResult ? $value->jsonSerialize() : $value;
    }

    /**
     * @param  list<array{key: string, label: string}>  $axes
     * @param  list<array{name: string, data: list<int|float>}>  $datasets
     */
    protected function result(array $axes, array $datasets): RadarResult
    {
        return new RadarResult($axes, $datasets);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function count(LatheRequest $request, Builder|Model|string $model, Expression|string $groupBy, Expression|string|null $column = null): RadarResult
    {
        return $this->fromGroups($this->aggregateQuery($model)->count($column)->groupBy($groupBy));
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sum(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): RadarResult
    {
        return $this->fromGroups($this->aggregateQuery($model)->sum($column)->groupBy($groupBy));
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function average(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): RadarResult
    {
        return $this->fromGroups($this->aggregateQuery($model)->average($column)->groupBy($groupBy));
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function max(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): RadarResult
    {
        return $this->fromGroups($this->aggregateQuery($model)->max($column)->groupBy($groupBy));
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function min(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): RadarResult
    {
        return $this->fromGroups($this->aggregateQuery($model)->min($column)->groupBy($groupBy));
    }

    /**
     * One axis per group and one dataset of the group numbers.
     */
    protected function fromGroups(AggregateQuery $query): RadarResult
    {
        $groups = $query->partition()->data;

        return new RadarResult(
            array_map(fn (string $key) => ['key' => $key, 'label' => $key], array_map('strval', array_keys($groups))),
            [['name' => Lathe::__('Value'), 'data' => array_values($groups)]],
        );
    }
}
