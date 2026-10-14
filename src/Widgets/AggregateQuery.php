<?php

namespace Lathe\Widgets;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Lathe\Query\DateBucket;
use Lathe\Support\Timezone;
use Lathe\Widgets\Results\BarResult;
use Lathe\Widgets\Results\PartitionResult;
use Lathe\Widgets\Results\TrendResult;
use Lathe\Widgets\Results\ValueResult;
use LogicException;

/**
 * The numbers behind a widget: one aggregate of a model's rows as a value, a trend over time,
 * bars or partitions by a column. Every call is one query, and dates bucket in the user's zone.
 */
class AggregateQuery
{
    /**
     * The most points a trend holds, and the most groups a bar or partition names.
     */
    public const MAX_POINTS = 366;

    public const MAX_GROUPS = 20;

    protected string $function = 'count';

    protected Expression|string $column = '*';

    protected ?string $dateColumn = null;

    protected ?string $timezone = null;

    protected TrendUnit $unit = TrendUnit::Day;

    protected int $range = 30;

    protected Expression|string|null $groupColumn = null;

    /** @var array<string, string> */
    protected array $colors = [];

    protected ?Closure $callback = null;

    /**
     * @param  Builder<Model>  $builder
     */
    final protected function __construct(protected Builder $builder)
    {
        //
    }

    /**
     * @param  Builder<Model>|Model|class-string<Model>  $model
     */
    public static function for(Builder|Model|string $model): static
    {
        if ($model instanceof Builder) {
            return new static(clone $model);
        }

        return new static(($model instanceof Model ? $model : new $model)->newQuery());
    }

    public function count(Expression|string|null $column = null): static
    {
        return $this->aggregateOf('count', $column ?? '*');
    }

    public function sum(Expression|string $column): static
    {
        return $this->aggregateOf('sum', $column);
    }

    public function average(Expression|string $column): static
    {
        return $this->aggregateOf('avg', $column);
    }

    public function min(Expression|string $column): static
    {
        return $this->aggregateOf('min', $column);
    }

    public function max(Expression|string $column): static
    {
        return $this->aggregateOf('max', $column);
    }

    /**
     * The column that dates a row, the model's created-at column unless set.
     */
    public function dateColumn(string $column): static
    {
        $this->dateColumn = $column;

        return $this;
    }

    /**
     * The zone buckets and periods are cut in, the panel user's zone unless set.
     */
    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Cover this many buckets of the unit, the current one included.
     */
    public function by(TrendUnit $unit, int $count): static
    {
        if ($count > self::MAX_POINTS) {
            throw new LogicException('A trend has at most '.self::MAX_POINTS." points, {$count} were asked for.");
        }

        $this->unit = $unit;
        $this->range = $count;

        return $this;
    }

    public function byMinutes(int $count): static
    {
        return $this->by(TrendUnit::Minute, $count);
    }

    public function byHours(int $count): static
    {
        return $this->by(TrendUnit::Hour, $count);
    }

    public function byDays(int $count): static
    {
        return $this->by(TrendUnit::Day, $count);
    }

    public function byWeeks(int $count): static
    {
        return $this->by(TrendUnit::Week, $count);
    }

    public function byMonths(int $count): static
    {
        return $this->by(TrendUnit::Month, $count);
    }

    public function byYears(int $count): static
    {
        return $this->by(TrendUnit::Year, $count);
    }

    /**
     * The column bar() and partition() group by.
     */
    public function groupBy(Expression|string $column): static
    {
        $this->groupColumn = $column;

        return $this;
    }

    /**
     * @param  array<string, string>  $colors
     */
    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /**
     * Narrow the rows, or join other tables.
     *
     * @param  callable(Builder<Model>): mixed  $callback
     */
    public function query(callable $callback): static
    {
        $this->callback = $callback(...);

        return $this;
    }

    /**
     * The tables the query reads, for the cache to know when a write makes a result stale.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $base = $this->scoped();
        $names = [$base->from];

        foreach ($base->joins ?? [] as $join) {
            if ($join instanceof JoinClause) {
                $names[] = $join->table;
            }
        }

        $tables = [];

        foreach ($names as $name) {
            if (is_string($name)) {
                $tables[] = (string) preg_replace('/\s+as\s+.*$/i', '', $name);
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * The aggregate over the last range, with the aggregate over the range before it.
     */
    public function value(): ValueResult
    {
        $now = CarbonImmutable::now($this->zone());
        $start = $now->sub($this->unit->value, $this->range);
        $before = $now->sub($this->unit->value, 2 * $this->range);
        $column = $this->qualifiedDateColumn();

        return new ValueResult(
            $this->aggregate($this->scoped()->where($column, '>=', $this->utc($start))->where($column, '<=', $this->utc($now))),
            $this->aggregate($this->scoped()->where($column, '>=', $this->utc($before))->where($column, '<', $this->utc($start))),
        );
    }

    /**
     * The aggregate between two moments (both inclusive), with the one between two other moments.
     */
    public function valueBetween(DateTimeInterface $start, DateTimeInterface $end, ?DateTimeInterface $previousStart = null, ?DateTimeInterface $previousEnd = null): ValueResult
    {
        $column = $this->qualifiedDateColumn();
        $previous = $previousStart !== null && $previousEnd !== null
            ? $this->aggregate($this->scoped()->whereBetween($column, [$this->utc($previousStart), $this->utc($previousEnd)]))
            : null;

        return new ValueResult(
            $this->aggregate($this->scoped()->whereBetween($column, [$this->utc($start), $this->utc($end)])),
            $previous,
        );
    }

    /**
     * The aggregate of each bucket of the unit, the current bucket last, with 0 for a bucket without rows.
     */
    public function trend(): TrendResult
    {
        $zone = $this->zone();
        $unit = $this->unit;
        $now = CarbonImmutable::now($zone);
        $first = $unit->shift($unit->startOf($now), 1 - $this->range);
        $end = $unit->shift($unit->startOf($now), 1);
        $column = $this->qualifiedDateColumn();
        $connection = $this->builder->getModel()->getConnection();
        $bucket = DateBucket::zoned($connection, $column, $unit, $zone, $first, $end);
        $clockTime = $unit === TrendUnit::Minute || $unit === TrendUnit::Hour;
        $offset = $clockTime ? DateBucket::offset($connection, $column, $zone, $first, $end) : null;

        $query = $this->scoped()
            ->addSelect($this->sql("{$bucket} as bucket_label"), $this->sql($this->aggregateSql().' as aggregate_value'))
            ->where($column, '>=', $this->utc($first))
            ->where($column, '<', $this->utc($end))
            ->groupBy($this->sql($bucket));

        if ($offset !== null) {
            $query->addSelect($this->sql("{$offset} as bucket_offset"));

            // A fixed offset is a number, which GROUP BY would read as a column position.
            if (! is_numeric($offset)) {
                $query->groupBy($this->sql($offset));
            }
        }

        $found = [];

        foreach ($query->get() as $row) {
            $label = is_string($row->bucket_label) ? $row->bucket_label : '';

            if ($offset !== null) {
                $seconds = is_numeric($row->bucket_offset) ? (int) $row->bucket_offset : 0;
                $label = $unit->key(CarbonImmutable::parse($label, 'UTC')->subSeconds($seconds)->setTimezone($zone));
            }

            $found[$label] = $this->number($row->aggregate_value) ?? 0;
        }

        $points = [];

        for ($step = 0; $step < $this->range; $step++) {
            $key = $unit->key($unit->shift($first, $step));
            $points[$key] = $found[$key] ?? 0;
        }

        return new TrendResult($points);
    }

    public function bar(): BarResult
    {
        return new BarResult($this->groups('bar'), $this->colors);
    }

    public function partition(): PartitionResult
    {
        return new PartitionResult($this->groups('partition'), $this->colors);
    }

    /**
     * The aggregate of each value of the group column, largest first. Past 20 groups the rest
     * merge into "Other" when they add up (count, sum) and are dropped when they do not.
     *
     * @return array<string, int|float>
     */
    protected function groups(string $terminal): array
    {
        if ($this->groupColumn === null) {
            throw new LogicException("Call groupBy() before {$terminal}().");
        }

        $group = $this->wrapped($this->groupColumn);

        $rows = $this->scoped()
            ->addSelect($this->sql("{$group} as group_label"), $this->sql($this->aggregateSql().' as aggregate_value'))
            ->groupBy($this->sql($group))
            ->get();

        $groups = [];

        foreach ($rows as $row) {
            $label = $row->group_label === null ? 'N/A' : (is_scalar($row->group_label) ? (string) $row->group_label : '');
            $groups[$label] = $this->number($row->aggregate_value) ?? 0;
        }

        // Ties sort by label so the same data gives the same order on every driver.
        uksort($groups, fn (string|int $a, string|int $b) => $groups[$b] <=> $groups[$a] ?: strcmp((string) $a, (string) $b));

        if (count($groups) <= self::MAX_GROUPS) {
            return $groups;
        }

        if (! in_array($this->function, ['count', 'sum'], true)) {
            return array_slice($groups, 0, self::MAX_GROUPS, true);
        }

        $named = array_slice($groups, 0, self::MAX_GROUPS - 1, true);
        $named['Other'] = array_sum(array_slice($groups, self::MAX_GROUPS - 1));

        return $named;
    }

    protected function aggregateOf(string $function, Expression|string $column): static
    {
        $this->function = $function;
        $this->column = $column;

        return $this;
    }

    /**
     * The model's query with the callback applied, as a base query with no ordering and no columns.
     */
    protected function scoped(): QueryBuilder
    {
        $builder = clone $this->builder;

        if ($this->callback !== null) {
            $result = ($this->callback)($builder);
            $builder = $result instanceof Builder ? $result : $builder;
        }

        $base = $builder->toBase()->reorder();
        $base->columns = null;

        return $base;
    }

    protected function aggregate(QueryBuilder $query): int|float|null
    {
        $row = $query->addSelect($this->sql($this->aggregateSql().' as aggregate_value'))->first();

        return $this->number($row->aggregate_value ?? null);
    }

    /**
     * SQL this class wrote from wrapped identifiers and its own literals.
     */
    protected function sql(string $sql): Expression
    {
        return new class($sql) implements Expression
        {
            public function __construct(protected string $sql)
            {
                //
            }

            public function getValue(Grammar $grammar): string
            {
                return $this->sql;
            }
        };
    }

    protected function aggregateSql(): string
    {
        return strtoupper($this->function).'('.($this->column === '*' ? '*' : $this->wrapped($this->column)).')';
    }

    /**
     * A database number as a PHP one: a count is an int, the other functions are floats, and a
     * function over no rows is null, except a sum and a count, which are 0.
     */
    protected function number(mixed $value): int|float|null
    {
        if ($value === null) {
            return in_array($this->function, ['count', 'sum'], true) ? ($this->function === 'count' ? 0 : 0.0) : null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return $this->function === 'count' ? (int) $value : (float) $value;
    }

    /**
     * A column as SQL: table-qualified for the model's own table and wrapped by the connection's grammar.
     */
    protected function wrapped(Expression|string $column): string
    {
        $grammar = $this->builder->getQuery()->getGrammar();

        if ($column instanceof Expression) {
            return (string) $column->getValue($grammar);
        }

        return $grammar->wrap($this->qualified($column));
    }

    protected function qualified(string $column): string
    {
        return str_contains($column, '.') ? $column : $this->builder->getModel()->qualifyColumn($column);
    }

    protected function qualifiedDateColumn(): string
    {
        return $this->qualified($this->dateColumn ?? $this->builder->getModel()->getCreatedAtColumn() ?? 'created_at');
    }

    protected function zone(): string
    {
        return $this->timezone ?? app(Timezone::class)->forRequest(request());
    }

    /**
     * A moment as the UTC text the database holds.
     */
    protected function utc(DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->utc()->format('Y-m-d H:i:s');
    }
}
