<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * A column of a Resource: where its value comes from and which cell component draws it.
 *
 * @phpstan-consistent-constructor
 */
class Field implements JsonSerializable
{
    protected string $component = 'text';

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
     * @return array{key: string, label: string, component: string}
     */
    public function jsonSerialize(): array
    {
        return ['key' => $this->key(), 'label' => $this->label, 'component' => $this->component];
    }
}
