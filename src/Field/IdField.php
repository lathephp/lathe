<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * The record key. A big integer key travels as a string so JavaScript never rounds it.
 */
class IdField extends Field
{
    protected string $component = 'id-field';

    protected bool $bigInt = false;

    public function __construct(string $label = 'ID', string|Closure|null $attribute = 'id', ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->sortable = true;
        $this->exceptOnForms();
    }

    /**
     * A key field on no page: it only carries the key.
     */
    public static function hidden(string $label = 'ID', string $attribute = 'id'): static
    {
        return (new static($label, $attribute))->onlyOn();
    }

    /**
     * The key field of a model: its key name, and a string value when the key passes 2^53 - 1.
     */
    public static function forModel(Model $model, string $label = 'ID'): static
    {
        $field = new static($label, $model->getKeyName());
        $key = $model->getKey();

        return is_int($key) && abs($key) > DefaultLifecycle::MAX_SAFE_INTEGER ? $field->asBigInt() : $field;
    }

    public function asBigInt(): static
    {
        $this->mutate();
        $this->bigInt = true;

        return $this;
    }

    public function resolve(object $record, ?string $attribute = null): mixed
    {
        $value = parent::resolve($record, $attribute);

        return $this->bigInt && is_int($value) ? (string) $value : $value;
    }
}
