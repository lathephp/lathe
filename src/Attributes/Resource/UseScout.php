<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Search through Scout; null detects the trait.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class UseScout implements ResourceAttribute
{
    public function __construct(
        public ?bool $use = true,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->useScout($this->use);
    }
}
