<?php

namespace Lathe\Field\Relations;

use Closure;

/**
 * The children of a hasMany relation, listed in a panel on detail and created from it.
 */
class HasManyField extends RelationField
{
    protected string $component = 'has-many-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->onlyOnDetail();
    }

    public function kind(): string
    {
        return 'hasMany';
    }

    public function createsThroughPanel(): bool
    {
        return true;
    }
}
