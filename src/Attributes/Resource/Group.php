<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * The sidebar group.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Group implements ResourceAttribute
{
    public function __construct(
        public string $group,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->group($this->group);
    }
}
