<?php

namespace Lathe\Field\Relations;

use Closure;

/**
 * A hasManyThrough relation: a read-only panel on detail.
 */
class HasManyThroughField extends RelationField
{
    protected string $component = 'has-many-through-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->onlyOnDetail();
    }

    public function kind(): string
    {
        return 'hasManyThrough';
    }
}
