<?php

namespace Lathe\Widgets;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\Timezone;
use Lathe\Widgets\Results\TrendResult;
use LogicException;

/**
 * A card that draws a number per time bucket as a line or an area.
 */
abstract class TrendWidget extends Widget
{
    protected ?string $component = 'trend-widget';

    protected string|int|null $defaultRange = null;

    protected bool $twelveHourTime = false;

    protected bool $lineOnly = false;

    protected bool $stacked = false;

    protected bool $stackedExpand = false;

    protected ?string $curveType = null;

    protected bool $showAxes = false;

    protected bool $showDots = false;

    protected ?string $dotStyle = null;

    protected bool $dotColors = false;

    protected bool $showLabels = false;

    protected ?string $labelKey = null;

    /**
     * @return array<int|string, string>
     */
    public function ranges(): array
    {
        return [30 => '30 Days', 60 => '60 Days', 90 => '90 Days'];
    }

    /**
     * The range shown until the user picks one, the first range key unless set.
     */
    public function defaultRange(string|int $key): static
    {
        $this->defaultRange = $key;

        return $this;
    }

    public function lineOnly(bool $on = true): static
    {
        $this->lineOnly = $on;

        return $this;
    }

    public function stacked(bool $on = true): static
    {
        $this->stacked = $on;

        return $this;
    }

    /**
     * Stacks the series to 100%, each showing its share of the total.
     */
    public function stackedExpand(bool $on = true): static
    {
        $this->stacked = true;
        $this->stackedExpand = $on;

        return $this;
    }

    /**
     * One of monotone, linear, step or natural.
     */
    public function curveType(string $type): static
    {
        if (! in_array($type, ['monotone', 'linear', 'step', 'natural'], true)) {
            throw new LogicException(sprintf('[%s] curveType() takes monotone, linear, step or natural; "%s" was given.', static::class, $type));
        }

        $this->curveType = $type;

        return $this;
    }

    public function showAxes(bool $on = true): static
    {
        $this->showAxes = $on;

        return $this;
    }

    public function showDots(bool $on = true): static
    {
        $this->showDots = $on;

        return $this;
    }

    /**
     * @param  'default'|'custom'  $style
     */
    public function dotStyle(string $style): static
    {
        $this->dotStyle = $style;
        $this->showDots = true;

        return $this;
    }

    /**
     * Colours each dot from the chart palette.
     */
    public function dotColors(bool $on = true): static
    {
        $this->dotColors = $on;
        $this->showDots = true;

        return $this;
    }

    public function showLabels(bool $on = true): static
    {
        $this->showLabels = $on;

        return $this;
    }

    /**
     * The key whose text labels each point, in place of its value.
     */
    public function labelKey(string $key): static
    {
        $this->labelKey = $key;

        return $this;
    }

    /**
     * Writes hourly labels as 2 PM, not 14:00.
     */
    public function twelveHourTime(bool $on = true): static
    {
        $this->twelveHourTime = $on;

        return $this;
    }

    public function descriptor(): array
    {
        return [
            ...parent::descriptor(),
            'defaultRange' => $this->defaultRange,
            'lineOnly' => $this->lineOnly,
            'stacked' => $this->stacked,
            'stackedExpand' => $this->stackedExpand,
            'curveType' => $this->curveType,
            'showAxes' => $this->showAxes,
            'showDots' => $this->showDots,
            'dotStyle' => $this->dotStyle,
            'dotColors' => $this->dotColors,
            'showLabels' => $this->showLabels,
            'labelKey' => $this->labelKey,
            'twelveHourTime' => $this->twelveHourTime,
        ];
    }

    /**
     * A trend result goes out as its payload, the points with the options the client draws them by.
     */
    public function resolve(LatheRequest $request): mixed
    {
        $value = parent::resolve($request);

        if (! $value instanceof TrendResult) {
            return $value;
        }

        if ($this->roundingSet) {
            $value->rounded($this->roundNumber(...), $this->roundingPrecision, $this->roundingMode);
        }

        return $value->payload();
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function countByMinutes(LatheRequest $request, Builder|Model|string $model, Expression|string|null $column = null, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'count', TrendUnit::Minute, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function countByHours(LatheRequest $request, Builder|Model|string $model, Expression|string|null $column = null, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'count', TrendUnit::Hour, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function countByDays(LatheRequest $request, Builder|Model|string $model, Expression|string|null $column = null, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'count', TrendUnit::Day, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function countByWeeks(LatheRequest $request, Builder|Model|string $model, Expression|string|null $column = null, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'count', TrendUnit::Week, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function countByMonths(LatheRequest $request, Builder|Model|string $model, Expression|string|null $column = null, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'count', TrendUnit::Month, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sumByMinutes(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'sum', TrendUnit::Minute, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sumByHours(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'sum', TrendUnit::Hour, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sumByDays(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'sum', TrendUnit::Day, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sumByWeeks(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'sum', TrendUnit::Week, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sumByMonths(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'sum', TrendUnit::Month, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function averageByMinutes(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'average', TrendUnit::Minute, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function averageByHours(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'average', TrendUnit::Hour, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function averageByDays(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'average', TrendUnit::Day, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function averageByWeeks(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'average', TrendUnit::Week, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function averageByMonths(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'average', TrendUnit::Month, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function maxByMinutes(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'max', TrendUnit::Minute, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function maxByHours(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'max', TrendUnit::Hour, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function maxByDays(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'max', TrendUnit::Day, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function maxByWeeks(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'max', TrendUnit::Week, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function maxByMonths(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'max', TrendUnit::Month, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function minByMinutes(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'min', TrendUnit::Minute, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function minByHours(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'min', TrendUnit::Hour, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function minByDays(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'min', TrendUnit::Day, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function minByWeeks(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'min', TrendUnit::Week, $column, $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function minByMonths(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): TrendResult
    {
        return $this->aggregate($request, $model, 'min', TrendUnit::Month, $column, $dateColumn);
    }

    /**
     * One bucket of the unit per step of the requested range, the range being a number of buckets.
     *
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    private function aggregate(LatheRequest $request, Builder|Model|string $model, string $function, TrendUnit $unit, Expression|string|null $column, ?string $dateColumn): TrendResult
    {
        $query = $this->aggregateQuery($model)
            ->timezone(app(Timezone::class)->forRequest($request))
            ->by($unit, $this->selectedRange($request));

        if ($dateColumn !== null) {
            $query->dateColumn($dateColumn);
        }

        match ($function) {
            'count' => $query->count($column),
            'sum' => $query->sum($column ?? '*'),
            'average' => $query->average($column ?? '*'),
            'max' => $query->max($column ?? '*'),
            default => $query->min($column ?? '*'),
        };

        return $query->trend();
    }

    /**
     * The request's range as a number of buckets. Anything else, and more than a trend holds,
     * means the default range.
     */
    private function selectedRange(LatheRequest $request): int
    {
        $range = $request->query('range');

        if (is_numeric($range) && $range >= 1 && $range <= AggregateQuery::MAX_POINTS) {
            return (int) $range;
        }

        $default = $this->defaultRange ?? array_key_first($this->ranges());

        return is_numeric($default) ? (int) $default : 30;
    }
}
