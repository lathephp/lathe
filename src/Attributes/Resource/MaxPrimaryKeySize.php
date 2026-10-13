<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * The largest integer key the key search accepts.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class MaxPrimaryKeySize implements ResourceAttribute
{
    public function __construct(
        public int $size,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->maxPrimaryKeySize($this->size);
    }
}
