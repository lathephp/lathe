<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Seconds the search box waits before it queries.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Debounce implements ResourceAttribute
{
    public function __construct(
        public float $seconds,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->debounce($this->seconds);
    }
}
