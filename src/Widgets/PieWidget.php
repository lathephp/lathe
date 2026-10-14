<?php

namespace Lathe\Widgets;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\PieResult;

/**
 * A card of pie or donut segments, one per group.
 */
abstract class PieWidget extends Widget
{
    protected ?string $component = 'pie-widget';

    protected string $height = 'double';

    protected bool $separatorNone = false;

    /** 'inline', 'custom' or 'list'; the three modes exclude each other */
    protected ?string $labelMode = null;

    protected bool $activeDonut = false;

    protected bool $showTotal = false;

    /**
     * Draws no stroke between the segments.
     */
    public function separatorNone(): static
    {
        $this->separatorNone = true;

        return $this;
    }

    /**
     * Draws each label on its segment.
     */
    public function showInlineLabel(): static
    {
        $this->labelMode = 'inline';

        return $this;
    }

    /**
     * Draws each label and value on its segment.
     */
    public function showCustomLabel(): static
    {
        $this->labelMode = 'custom';

        return $this;
    }

    /**
     * Lists the labels outside the segments.
     */
    public function showLabelList(): static
    {
        $this->labelMode = 'list';

        return $this;
    }

    /**
     * Highlights the first segment of a donut with a stroke of width 5.
     */
    public function activeDonut(): static
    {
        $this->activeDonut = true;

        return $this;
    }

    /**
     * Shows the total in the centre of a donut.
     */
    public function showTotal(): static
    {
        $this->showTotal = true;

        return $this;
    }

    public function resolve(LatheRequest $request): mixed
    {
        $value = parent::resolve($request);

        return $value instanceof PieResult ? $value->jsonSerialize() : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function descriptor(): array
    {
        return [
            ...parent::descriptor(),
            ...($this->separatorNone ? ['separatorNone' => true] : []),
            ...($this->labelMode === null ? [] : ['labelMode' => $this->labelMode]),
            ...($this->activeDonut ? ['activeDonut' => ['strokeWidth' => 5, 'activeIndex' => 0]] : []),
            ...($this->showTotal ? ['showTotal' => true] : []),
        ];
    }

    /**
     * @param  array<string, int|float>  $segments
     */
    protected function result(array $segments): PieResult
    {
        return new PieResult($segments);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function count(LatheRequest $request, Builder|Model|string $model, Expression|string $groupBy, Expression|string|null $column = null): PieResult
    {
        return $this->fromPartition(AggregateQuery::for($model)->count($column)->groupBy($groupBy));
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sum(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): PieResult
    {
        return $this->fromPartition(AggregateQuery::for($model)->sum($column)->groupBy($groupBy));
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function average(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): PieResult
    {
        return $this->fromPartition(AggregateQuery::for($model)->average($column)->groupBy($groupBy));
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function max(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): PieResult
    {
        return $this->fromPartition(AggregateQuery::for($model)->max($column)->groupBy($groupBy));
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function min(LatheRequest $request, Builder|Model|string $model, Expression|string $column, Expression|string $groupBy): PieResult
    {
        return $this->fromPartition(AggregateQuery::for($model)->min($column)->groupBy($groupBy));
    }

    protected function fromPartition(AggregateQuery $query): PieResult
    {
        $groups = $query->partition();

        return new PieResult($groups->data, $groups->colors);
    }
}
