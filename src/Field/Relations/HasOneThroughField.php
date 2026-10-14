<?php

namespace Lathe\Field\Relations;

use Closure;

/**
 * A hasOneThrough relation: a read-only panel on detail.
 */
class HasOneThroughField extends RelationField
{
    protected string $component = 'has-one-through-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->onlyOnDetail();
    }

    public function kind(): string
    {
        return 'hasOneThrough';
    }
}
