<?php

namespace Lathe\Summaries;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lathe\Field\Field;
use Lathe\Groups\Group;
use Lathe\Query\DateBucket;
use Lathe\Query\ResourceQuery;
use Lathe\Support\Timezone;
use LogicException;

/**
 * The footer totals of an index: "All records" from one batched query over the scoped
 * rows, and "This page" from the rows already loaded.
 */
class ResourceSummaries
{
    public const string CAPPED_MESSAGE = 'Narrow the filters to total all rows';

    /** Group keys one subtotal query reads at most, besides null. */
    public const int MAX_GROUPS = 100;

    /**
     * @param  array<mixed>  $fields
     */
    public function __construct(
        protected ResourceQuery $query,
        protected array $fields,
        protected int $cap,
    ) {
        //
    }

    /**
     * The fields that carry a summary, which decide whether the footer exists at all.
     *
     * @param  array<mixed>  $fields
     * @return list<Field>
     */
    public static function summarized(array $fields): array
    {
        return array_values(array_filter(
            $fields,
            fn (mixed $field) => $field instanceof Field && $field->getSummaries() !== [],
        ));
    }

    /**
     * Null when no field is summarized. Above the cap only the page is totalled.
     *
     * @param  Collection<int, Model>  $rows
     * @return array{allRecords: array<string, list<array{label: string|null, value: string|null}>>|null, page: array<string, list<array{label: string|null, value: string|null}>>|null, message: string|null}|null
     */
    public function compute(Collection $rows, bool $paged): ?array
    {
        $fields = static::summarized($this->fields);

        if ($fields === []) {
            return null;
        }

        $capped = $this->cappedCount() > $this->cap;

        return [
            'allRecords' => $capped ? null : $this->allRecords($fields),
            'page' => $paged || $capped ? $this->page($fields, $rows) : null,
            'message' => $capped ? self::CAPPED_MESSAGE : null,
        ];
    }

    /**
     * The subtotal of each group on the page, keyed by group key, from one GROUP BY query
     * limited to those keys. A narrowed or to-many summary has no subtotal.
     *
     * @param  Collection<int, Model>  $rows
     * @return array<string, array<string, list<array{label: string|null, value: string|null}>>>
     */
    public function computeGrouped(Collection $rows, Group $group): array
    {
        $scoped = $this->query->scoped();
        $model = $scoped->getModel();
        $keys = array_slice(array_values(array_unique(array_map(fn (Model $row) => $group->keyFor($row), $rows->all()))), 0, self::MAX_GROUPS);
        $entries = [];
        /** @var list<Field> $fields */
        $fields = array_values(array_filter($this->fields, fn (mixed $field) => $field instanceof Field));

        foreach ($fields as $f => $field) {
            foreach ($field->getSummaries() as $s => $summary) {
                if ($summary->getQuery() === null && ! $this->target($model, $field->getSortableUriKey())[1] instanceof HasMany) {
                    $entries["{$f}_{$s}"] = [$field, $summary];
                }
            }
        }

        if ($keys === [] || $entries === []) {
            return [];
        }

        $base = $scoped->toBase()->reorder();
        $joined = [];
        $owner = $model;

        if (($name = $group->relation()) !== null) {
            /** @var BelongsTo<Model, Model> $relation */
            $relation = $model->{$name}();
            $joined[] = $name;
            $owner = $relation->getRelated();
            $base->leftJoin($owner->getTable(), $relation->getQualifiedForeignKeyName(), '=', $relation->getQualifiedOwnerKeyName());
        }

        [$expression, $bindings] = $this->groupKeyColumn($base, $group, $owner, $keys);
        $columns = [];

        foreach ($entries as $id => [$field, $summary]) {
            [$column, $relation] = $this->target($model, $field->getSortableUriKey());

            if ($relation instanceof BelongsTo && ! in_array($relation->getRelationName(), $joined, true)) {
                $joined[] = $relation->getRelationName();
                $base->leftJoin($relation->getRelated()->getTable(), $relation->getQualifiedForeignKeyName(), '=', $relation->getQualifiedOwnerKeyName());
            }

            foreach ($summary->functions() as $n => $function) {
                $columns[] = $this->expression($function, $column, "lathe_{$id}_{$n}");
            }
        }

        $results = [];

        // Grouped by the alias, so a key expression with bindings matches itself on PostgreSQL.
        foreach ($base->select([$this->sql($expression.' as lathe_group'), ...$columns])->addBinding($bindings, 'select')->groupBy('lathe_group')->get() as $row) {
            $row = (array) $row;
            $results[$group->normalizeKey($row['lathe_group'])] = $row;
        }

        $subtotals = [];

        foreach ($keys as $key) {
            foreach (isset($results[$key]) ? $entries : [] as $id => [$field, $summary]) {
                $subtotals[$key][$field->key()][] = [
                    'label' => $summary->getLabel(),
                    'value' => $summary->present(array_map(fn (int $n) => $results[$key]["lathe_{$id}_{$n}"] ?? null, array_keys($summary->functions()))),
                ];
            }
        }

        return $subtotals;
    }

    /**
     * The SQL of a row's group key, with its bindings, and the WHERE that keeps the page's keys.
     * A datetime day group buckets by the user's local day through UTC range conditions.
     *
     * @param  list<string>  $keys
     * @return array{0: string, 1: list<mixed>}
     */
    protected function groupKeyColumn(QueryBuilder $base, Group $group, Model $owner, array $keys): array
    {
        $column = $owner->qualifyColumn($group->attribute());
        $wrapped = $base->getGrammar()->wrap($column);
        $values = array_values(array_filter($keys, fn (string $key) => $key !== Group::NULL_KEY));
        $withNull = count($values) < count($keys);

        if (! $group->bucketsLocalDay($owner)) {
            $expression = $group->isDate() ? DateBucket::day($owner->getConnection(), $column) : $wrapped;
            $base->where(fn (QueryBuilder $query) => $query->whereIn($this->sql($expression), $values)
                ->when($withNull, fn (QueryBuilder $query) => $query->orWhereNull($column)));

            return [$expression, []];
        }

        $cases = [];
        $bindings = [];
        $ranges = [];

        foreach ($values as $day) {
            [$start, $end] = app(Timezone::class)->dayBounds($day, $group->timezone());
            $range = [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
            $ranges[] = $range;
            $cases[] = "when {$wrapped} >= ? and {$wrapped} < ? then ?";
            array_push($bindings, $range[0], $range[1], $day);
        }

        $base->where(function (QueryBuilder $query) use ($column, $ranges, $withNull) {
            foreach ($ranges as [$start, $end]) {
                $query->orWhere(fn (QueryBuilder $range) => $range->where($column, '>=', $start)->where($column, '<', $end));
            }

            $query->when($withNull, fn (QueryBuilder $query) => $query->orWhereNull($column));
        });

        return [$cases === [] ? 'null' : 'case '.implode(' ', $cases).' end', $bindings];
    }

    /**
     * SQL this class wrote from quoted identifiers, for a select or a WHERE column.
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

    /**
     * Counts at most cap + 1 rows, so a huge result is never totalled.
     */
    protected function cappedCount(): int
    {
        $scoped = $this->query->scoped();

        return $scoped->getModel()->getConnection()->query()
            ->fromSub($scoped->toBase()->limit($this->cap + 1), 'lathe_count')
            ->count();
    }

    /**
     * @param  list<Field>  $fields
     * @return array<string, list<array{label: string|null, value: string|null}>>
     */
    protected function allRecords(array $fields): array
    {
        $batched = [];
        $values = [];

        foreach ($fields as $f => $field) {
            foreach ($field->getSummaries() as $s => $summary) {
                $closure = $summary->getQuery();

                if ($closure === null) {
                    $batched["{$f}_{$s}"] = [$field, $summary];

                    continue;
                }

                // A narrowed summary cannot share the batch's WHERE, so it runs alone.
                $query = $this->query->scoped();
                $closure($query);
                $values += $this->aggregate($query, ["{$f}_{$s}" => [$field, $summary]]);
            }
        }

        if ($batched !== []) {
            $values += $this->aggregate($this->query->scoped(), $batched);
        }

        $totals = [];

        foreach ($fields as $f => $field) {
            foreach ($field->getSummaries() as $s => $summary) {
                $totals[$field->key()][] = ['label' => $summary->getLabel(), 'value' => $values["{$f}_{$s}"]];
            }
        }

        return $totals;
    }

    /**
     * One SELECT with every aggregate: plain and to-one columns directly, to-many columns
     * in a subselect on the foreign key over the same rows' keys.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, array{0: Field, 1: Summary}>  $entries
     * @return array<string, string|null>
     */
    protected function aggregate(Builder $query, array $entries): array
    {
        $model = $query->getModel();
        $keys = $query->toBase()->reorder();
        $base = $query->toBase()->reorder();
        $columns = [];
        $joined = [];

        foreach ($entries as $id => [$field, $summary]) {
            [$column, $relation] = $this->target($model, $field->getSortableUriKey());

            if ($relation instanceof BelongsTo && ! in_array($relation->getRelationName(), $joined, true)) {
                $joined[] = $relation->getRelationName();
                $base->leftJoin(
                    $relation->getRelated()->getTable(),
                    $relation->getQualifiedForeignKeyName(),
                    '=',
                    $relation->getQualifiedOwnerKeyName(),
                );
            }

            foreach ($summary->functions() as $n => $function) {
                $alias = "lathe_{$id}_{$n}";

                $columns[$alias] = $relation instanceof HasMany
                    ? $relation->getRelated()->getConnection()->table($relation->getRelated()->getTable())
                        ->select($this->expression($function, $column))
                        ->whereIn($relation->getQualifiedForeignKeyName(), (clone $keys)->select($relation->getQualifiedParentKeyName()))
                    : $this->expression($function, $column, $alias);
            }
        }

        $row = (array) $base->select(array_values(array_filter($columns, fn (mixed $column) => $column instanceof Expression)))
            ->addSelect(array_filter($columns, fn (mixed $column) => ! $column instanceof Expression))
            ->first();

        $values = [];

        foreach ($entries as $id => [, $summary]) {
            $values[$id] = $summary->present(array_map(
                fn (int $n) => $row["lathe_{$id}_{$n}"] ?? null,
                array_keys($summary->functions()),
            ));
        }

        return $values;
    }

    /**
     * `sum("orders"."total_cents") as "alias"`, quoted by the connection's grammar.
     *
     * @param  'sum'|'avg'|'count'|'min'|'max'  $function
     */
    protected function expression(string $function, string $column, ?string $alias = null): Expression
    {
        return new class($function, $column, $alias) implements Expression
        {
            public function __construct(
                protected string $function,
                protected string $column,
                protected ?string $alias,
            ) {
                //
            }

            public function getValue(Grammar $grammar): string
            {
                $sql = $this->function.'('.$grammar->wrap($this->column).')';

                return $this->alias === null ? $sql : $sql.' as '.$grammar->wrap($this->alias);
            }
        };
    }

    /**
     * The page's totals from the loaded rows. A to-many column has no page value.
     *
     * @param  list<Field>  $fields
     * @param  Collection<int, Model>  $rows
     * @return array<string, list<array{label: string|null, value: string|null}>>
     */
    protected function page(array $fields, Collection $rows): array
    {
        $totals = [];

        foreach ($fields as $field) {
            $attribute = $field->getSortableUriKey();
            $model = $rows->first();

            if ($model !== null && $this->target($model, $attribute)[1] instanceof HasMany) {
                continue;
            }

            $values = array_values($rows->map(fn (Model $row) => data_get($row, $attribute))->all());

            foreach ($field->getSummaries() as $summary) {
                if ($summary->getQuery() === null) {
                    $totals[$field->key()][] = ['label' => $summary->getLabel(), 'value' => $summary->presentValues($values)];
                }
            }
        }

        return $totals;
    }

    /**
     * The qualified column of an attribute and, for a dotted one, its relation.
     *
     * @return array{0: string, 1: BelongsTo<Model, Model>|HasMany<Model, Model>|null}
     */
    protected function target(Model $model, string $attribute): array
    {
        if (! str_contains($attribute, '.')) {
            return [$model->qualifyColumn($attribute), null];
        }

        [$name, $column] = explode('.', $attribute, 2);
        $relation = method_exists($model, $name) ? $model->{$name}() : null;

        if (! $relation instanceof BelongsTo && ! $relation instanceof HasMany) {
            throw new LogicException("Cannot summarize [{$attribute}]: [{$name}] is not a belongsTo or hasMany relation.");
        }

        return [$relation->getRelated()->qualifyColumn($column), $relation];
    }
}
