<?php

namespace Lathe\Field;

use JsonSerializable;
use LogicException;

/**
 * What a dependsOn callback changes on one field for the form in front of the user. The
 * field itself stays frozen; the browser applies the patch.
 */
class FieldPatch implements JsonSerializable
{
    /** @var array{visible?: bool, value?: mixed, options?: list<array{value: string, label: string}>, readonly?: bool, required?: bool} */
    protected array $changes = [];

    public function __construct(public readonly Field $field)
    {
        //
    }

    public function show(): static
    {
        $this->changes['visible'] = true;

        return $this;
    }

    public function hide(): static
    {
        $this->changes['visible'] = false;

        return $this;
    }

    public function setValue(mixed $value): static
    {
        $this->changes['value'] = $value;

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $options  as a field's options(): a list, a value => label map
     */
    public function options(array $options): static
    {
        $normalized = OptionList::normalize($options, $this->field->label());

        if (count($normalized) > 100) {
            throw new LogicException("The dependent options of the field [{$this->field->label()}] hold more than 100 entries.");
        }

        $this->changes['options'] = array_map(fn (array $option) => ['value' => $option['value'], 'label' => $option['label']], $normalized);

        return $this;
    }

    public function readonly(bool $readonly = true): static
    {
        $this->changes['readonly'] = $readonly;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->changes['required'] = $required;

        return $this;
    }

    public function hasValue(): bool
    {
        return array_key_exists('value', $this->changes);
    }

    public function value(): mixed
    {
        return $this->changes['value'] ?? null;
    }

    public function withoutValue(): static
    {
        unset($this->changes['value']);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->changes;
    }
}
