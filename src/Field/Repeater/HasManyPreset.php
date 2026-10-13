<?php

namespace Lathe\Field\Repeater;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Lathe\Field\FieldLifecycle;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\ConfigStore;
use LogicException;

/**
 * Repeater rows kept as the children of a hasMany relation. Each row carries its child's key
 * as `_key`; the save syncs every row in a fixed number of queries, whatever the row count.
 *
 * @internal
 */
class HasManyPreset implements FieldLifecycle
{
    public function __construct(protected string $relationship, protected ?string $uniqueField = null, protected ?string $orderColumn = null)
    {
        //
    }

    /**
     * The children as rows, in the order column's order, each with its key under `_key`.
     *
     * @return list<array<string, mixed>>
     */
    public function extract(object $record, string $attribute): array
    {
        return array_values($this->children($record)->map(fn (Model $child) => [...$child->attributesToArray(), '_key' => $child->getKey()])->all());
    }

    public function present(mixed $raw, object $record, string $attribute): mixed
    {
        return $raw;
    }

    public function parse(LatheRequest $request, string $attribute): mixed
    {
        return $request->input($attribute);
    }

    /**
     * The parent has no column for the rows: the sync runs after the parent save, when its key is known.
     */
    public function persist(object $model, string $attribute, mixed $value): ?Closure
    {
        $rows = array_values(array_filter(is_array($value) ? $value : [], is_array(...)));

        return fn () => $this->sync($model, $rows);
    }

    /**
     * Load the children once, then update the rows that name one of them by `_key` or by the
     * unique field, create the rest, write the row index to the order column and delete the
     * loaded children the submission left out. A `_key` of another parent's child never matches.
     *
     * @param  list<array<mixed>>  $rows
     */
    protected function sync(object $model, array $rows): void
    {
        $relation = $this->relation($model);
        $children = $this->children($model);
        $byKey = $children->keyBy(fn (Model $child) => $this->keyOf($child->getKey()));
        $byUnique = $this->uniqueField === null ? null : $children->keyBy(fn (Model $child) => $this->keyOf($child->getAttribute($this->uniqueField)));
        $related = $relation->getRelated();
        $keyName = $related->getKeyName();

        $columns = [];
        $matched = [];
        $created = [];

        foreach ($rows as $index => $row) {
            $attributes = [];

            foreach ($row as $column => $value) {
                if (is_string($column) && $column !== '_key') {
                    $attributes[$column] = $value;
                }
            }

            if ($this->orderColumn !== null) {
                $attributes[$this->orderColumn] = $index;
            }

            $columns += array_fill_keys(array_keys($attributes), true);
            $unique = $this->uniqueField === null ? null : $attributes[$this->uniqueField] ?? null;
            $child = $byKey->get($this->keyOf($row['_key'] ?? null)) ?? $byUnique?->get($this->keyOf($unique));
            $key = $this->keyOf($child?->getKey());

            // A child that two rows name keeps the first; the second row becomes a new child.
            if ($child !== null && ! isset($matched[$key])) {
                $matched[$key] = $child->forceFill($attributes);
            } else {
                $created[] = $related->newInstance()->forceFill($attributes);
            }
        }

        $columns = array_keys($columns);
        $values = fn (Model $child) => [
            ...array_fill_keys($columns, null),
            ...array_intersect_key($child->getAttributes(), array_flip($columns)),
            $relation->getForeignKeyName() => $relation->getParentKey(),
        ];

        // An upsert row is an insert first, so a matched child also sends its stored columns:
        // one the form does not edit, such as a NOT NULL product_id, would fail the insert.
        $stored = fn (Model $child) => Arr::except($child->getRawOriginal(), array_filter([$related->getCreatedAtColumn(), $related->getUpdatedAtColumn()]));

        // Two upserts whatever the row count: one keyed by id updates, one without ids inserts.
        if ($matched !== []) {
            $relation->getQuery()->upsert(array_values(array_map(fn (Model $child) => [...$stored($child), $keyName => $child->getKey(), ...$values($child)], $matched)), [$keyName], $columns);
        }

        if ($created !== []) {
            $relation->getQuery()->upsert(array_map($values, $created), [$keyName], $columns);
        }

        $missing = $children->reject(fn (Model $child) => isset($matched[$this->keyOf($child->getKey())]))->modelKeys();

        if ($missing !== []) {
            $relation->getQuery()->whereKey($missing)->delete();
        }

        if ($model instanceof Model) {
            $model->unsetRelation($this->relationship);
        }
    }

    /**
     * A key or unique value as a lookup key; anything else matches nothing.
     */
    protected function keyOf(mixed $value): string
    {
        return is_int($value) || is_string($value) ? "#{$value}" : '';
    }

    /**
     * The loaded children, at most the repeater row cap.
     *
     * @return Collection<int, Model>
     */
    protected function children(object $record): Collection
    {
        $relation = $this->relation($record);

        return $relation->getQuery()
            ->orderBy($this->orderColumn ?? $relation->getRelated()->getKeyName())
            ->limit(app(ConfigStore::class)->integer('limits.repeater_rows'))
            ->get();
    }

    /**
     * @return HasMany<Model, Model>
     */
    protected function relation(object $record): HasMany
    {
        $relation = $record instanceof Model && method_exists($record, $this->relationship) ? $record->{$this->relationship}() : null;

        return $relation instanceof HasMany ? $relation : throw new LogicException("The repeater relation [{$this->relationship}] is not a hasMany relation of ".$record::class.'.');
    }
}
