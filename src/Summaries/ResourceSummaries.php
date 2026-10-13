<?php

namespace Lathe\Summaries;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Grammar;
use Lathe\Field\Field;
use Lathe\Query\ResourceQuery;
use LogicException;

/**
 * The footer totals of an index: "All records" from one batched query over the scoped
 * rows, and "This page" from the rows already loaded.
 */
class ResourceSummaries
{
    public const string CAPPED_MESSAGE = 'Narrow the filters to total all rows';

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
     * Null when no field is summarized. Above the cap only the page is totalled.
     *
     * @param  Collection<int, Model>  $rows
     * @return array{allRecords: array<string, list<array{label: string|null, value: string|null}>>|null, page: array<string, list<array{label: string|null, value: string|null}>>|null, message: string|null}|null
     */
    public function compute(Collection $rows, bool $paged): ?array
    {
        $fields = array_values(array_filter(
            $this->fields,
            fn (mixed $field) => $field instanceof Field && $field->getSummaries() !== [],
        ));

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
            [$column, $relation] = $this->target($model, $field->sortableUriKey());

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

        $row = (array) $base->select(array_values(array_filter($columns, fn ($column) => $column instanceof Expression)))
            ->addSelect(array_filter($columns, fn ($column) => ! $column instanceof Expression))
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
            $attribute = $field->sortableUriKey();
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
