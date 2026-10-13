<?php

namespace Lathe\Field\Repeater;

use Closure;
use Illuminate\Support\Str;
use Lathe\Field\Field;

/**
 * One block type of a polymorphic repeater: a row of this type holds `type` = key() and these fields.
 *
 * @phpstan-consistent-constructor
 */
class Repeatable
{
    protected ?string $icon = null;

    protected bool $confirmRemoval = false;

    /** @var list<Field>|null */
    protected ?array $resolved = null;

    /**
     * @param  list<Field>|(Closure(): list<Field>)  $fields
     */
    public function __construct(protected string $label, protected Closure|array $fields = [])
    {
        //
    }

    /**
     * @param  list<Field>|(Closure(): list<Field>)  $fields
     */
    public static function make(string $label, Closure|array $fields = []): static
    {
        return new static($label, $fields);
    }

    /**
     * The row's `type`: the kebab-case of the label.
     */
    public function key(): string
    {
        return Str::kebab($this->label);
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * @param  list<Field>|(Closure(): list<Field>)  $fields
     */
    public function fields(Closure|array $fields): static
    {
        $this->fields = $fields;
        $this->resolved = null;

        return $this;
    }

    /**
     * The block's fields; a Closure runs once.
     *
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->resolved ??= $this->fields instanceof Closure ? ($this->fields)() : $this->fields;
    }

    /**
     * A Lucide icon name shown on the block's rows and its add button.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Ask before a row of this type is removed.
     */
    public function confirmBeforeRemoval(bool $confirm = true): static
    {
        $this->confirmRemoval = $confirm;

        return $this;
    }

    /**
     * The block in the payload, each field drawn by the given callback.
     *
     * @param  Closure(Field): array<string, mixed>  $field
     * @return array{key: string, label: string, icon: string|null, confirmRemoval: bool, fields: list<array<string, mixed>>}
     */
    public function toPayload(Closure $field): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label,
            'icon' => $this->icon,
            'confirmRemoval' => $this->confirmRemoval,
            'fields' => array_map($field, $this->getFields()),
        ];
    }
}
