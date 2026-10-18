<?php

namespace Lathe\Widgets;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\Timezone;
use Lathe\Widgets\Results\ValueResult;

/**
 * A card with one number and the number for the period before it.
 */
abstract class ValueWidget extends Widget
{
    public const string TODAY = 'TODAY';

    public const string YESTERDAY = 'YESTERDAY';

    public const string THIS_WEEK = 'THIS_WEEK';

    public const string MTD = 'MTD';

    public const string QTD = 'QTD';

    public const string YTD = 'YTD';

    public const string ALL = 'ALL';

    protected ?string $component = 'value-widget';

    protected ?string $icon = null;

    protected string|int|null $defaultRange = null;

    /**
     * @return array<int|string, string>
     */
    public function ranges(): array
    {
        return [30 => '30 Days', 60 => '60 Days', 365 => '365 Days'];
    }

    /**
     * An icon the card draws beside its number.
     */
    public function icon(Icon $icon): static
    {
        $this->icon = $icon->value;

        return $this;
    }

    /**
     * The range used until the user picks one, the first range key unless set.
     */
    public function defaultRange(string|int $key): static
    {
        $this->defaultRange = $key;

        return $this;
    }

    public function defaultRangeKey(): int|string|null
    {
        return $this->defaultRange ?? parent::defaultRangeKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function descriptor(): array
    {
        return [...parent::descriptor(), 'icon' => $this->icon, 'defaultRange' => $this->defaultRange];
    }

    public function resolve(LatheRequest $request): mixed
    {
        $value = parent::resolve($request);

        if ($value instanceof ValueResult && $this->roundingSet) {
            $value->rounded($this->roundNumber(...), $this->roundingPrecision, $this->roundingMode);
        }

        return $value;
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function count(LatheRequest $request, Builder|Model|string $model, Expression|string|null $column = null, ?string $dateColumn = null): ValueResult
    {
        return $this->over($request, $this->aggregateQuery($model)->count($column), $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function sum(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): ValueResult
    {
        return $this->over($request, $this->aggregateQuery($model)->sum($column), $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function average(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): ValueResult
    {
        return $this->over($request, $this->aggregateQuery($model)->average($column), $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function min(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): ValueResult
    {
        return $this->over($request, $this->aggregateQuery($model)->min($column), $dateColumn);
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    protected function max(LatheRequest $request, Builder|Model|string $model, Expression|string $column, ?string $dateColumn = null): ValueResult
    {
        return $this->over($request, $this->aggregateQuery($model)->max($column), $dateColumn);
    }

    /**
     * The aggregate over the requested range and the one before it, 2 queries.
     */
    private function over(LatheRequest $request, AggregateQuery $query, ?string $dateColumn): ValueResult
    {
        $zone = app(Timezone::class)->forRequest($request);
        $query->timezone($zone);

        if ($dateColumn !== null) {
            $query->dateColumn($dateColumn);
        }

        $range = $this->selectedRange($request);

        if (is_int($range)) {
            return $query->byDays($range)->value();
        }

        $now = CarbonImmutable::now($zone);

        return $query->valueBetween(...match ($range) {
            self::TODAY => [$now->startOfDay(), $now, $now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            self::YESTERDAY => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay(), $now->subDays(2)->startOfDay(), $now->subDays(2)->endOfDay()],
            self::THIS_WEEK => [$now->startOfWeek(), $now, $now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek()],
            self::MTD => [$now->startOfMonth(), $now, $now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            self::QTD => [$now->startOfQuarter(), $now, $now->subQuarterNoOverflow()->startOfQuarter(), $now->subQuarterNoOverflow()->endOfQuarter()],
            self::YTD => [$now->startOfYear(), $now, $now->subYearNoOverflow()->startOfYear(), $now->subYearNoOverflow()->endOfYear()],
            default => [$now->subYears(100), $now],
        });
    }

    /**
     * The request's range: days as an int, or one of the range constants. Anything else, and
     * more days than a trend holds, means the default range.
     */
    private function selectedRange(LatheRequest $request): int|string
    {
        $range = $request->query('range');

        if (in_array($range, [self::TODAY, self::YESTERDAY, self::THIS_WEEK, self::MTD, self::QTD, self::YTD, self::ALL], true)) {
            return (string) $range;
        }

        if (is_numeric($range) && $range >= 1 && $range <= AggregateQuery::MAX_POINTS) {
            return (int) $range;
        }

        $first = $this->defaultRange ?? array_key_first($this->ranges()) ?? 30;

        return is_numeric($first) ? (int) $first : $first;
    }
}
