<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JsonSerializable;
use Lathe\Filters\FieldFilter;

/**
 * A column of a Resource: where its value comes from and which cell component draws it.
 *
 * @phpstan-consistent-constructor
 */
class Field implements JsonSerializable
{
    protected string $component = 'text';

    protected bool $sortable = false;

    protected bool $filterable = false;

    /**
     * @param  (Closure(Model): (string|int|float|bool|null))|null  $resolver
     */
    public function __construct(
        protected string $label,
        protected string $attribute,
        protected ?Closure $resolver = null,
    ) {
        //
    }

    /**
     * @param  (Closure(Model): (string|int|float|bool|null))|null  $resolver
     */
    public static function make(string $label, ?string $attribute = null, ?Closure $resolver = null): static
    {
        return new static($label, $attribute ?? Str::snake($label), $resolver);
    }

    public function component(string $component): static
    {
        $this->component = $component;

        return $this;
    }

    /**
     * Let the index sort on this field. A field is computed when it is not marked: its
     * resolver may build the value from anything, so only a field whose attribute is a
     * real column (or a column of a belongsTo relation) should be marked.
     */
    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * Give the index a min/max range filter on this field's column. Only for a numeric column.
     */
    public function filterable(bool $filterable = true): static
    {
        $this->filterable = $filterable;

        return $this;
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    public function makeFieldFilter(): FieldFilter
    {
        return new FieldFilter($this->label, $this->attribute);
    }

    /**
     * The token this field answers to in ?sort=: its attribute (total_cents, customer.name).
     */
    public function sortableUriKey(): string
    {
        return $this->attribute;
    }

    /**
     * The cell key: the attribute, or the relation name of a dotted one (customer.name gives customer).
     */
    public function key(): string
    {
        return Str::before($this->attribute, '.');
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * The value of this field on a record, read through dotted relations.
     */
    public function resolve(Model $model): string|int|float|bool|null
    {
        if ($this->resolver !== null) {
            return ($this->resolver)($model);
        }

        $value = data_get($model, $this->attribute);

        return is_scalar($value) || $value === null ? $value : (string) json_encode($value);
    }

    /**
     * The cell of one record: its value and the text shown when no component draws it.
     *
     * @return array{value: string|int|float|bool|null, display: string}
     */
    public function cell(Model $model): array
    {
        $value = $this->resolve($model);

        return ['value' => $value, 'display' => match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            default => (string) $value,
        }];
    }

    /**
     * @return array{key: string, label: string, component: string, sortable: bool, sortKey: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label,
            'component' => $this->component,
            'sortable' => $this->sortable,
            'sortKey' => $this->sortableUriKey(),
        ];
    }
}
