<?php

namespace Lathe\Field;

use Closure;

/**
 * A dependsOn rule: the attributes it watches, its callback, and the form it applies to.
 */
class Dependency
{
    /**
     * @param  list<string>  $attributes
     */
    public function __construct(
        public readonly array $attributes,
        public readonly Closure $callback,
        public readonly ?FieldContext $context = null,
    ) {
        //
    }

    public function appliesTo(FieldContext $context): bool
    {
        return $this->context === null || $this->context === $context;
    }
}
