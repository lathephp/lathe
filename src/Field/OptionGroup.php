<?php

namespace Lathe\Field;

/**
 * A labelled run of options inside a select: every option of it carries the label as its group.
 *
 * @phpstan-consistent-constructor
 */
class OptionGroup
{
    /**
     * @param  array<array-key, mixed>  $options  an indexed array, a value-to-label map or a BackedEnum class
     */
    public function __construct(public readonly string $label, public readonly array $options) {}

    /**
     * @param  array<array-key, mixed>  $options
     */
    public static function make(string $label, array $options): static
    {
        return new static($label, $options);
    }
}
