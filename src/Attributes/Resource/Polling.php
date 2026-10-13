<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Seconds between index refreshes.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Polling implements ResourceAttribute
{
    public function __construct(
        public int $seconds,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->polling($this->seconds);
    }
}
