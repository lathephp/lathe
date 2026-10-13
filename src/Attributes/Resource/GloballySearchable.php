<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * List the Resource in global search.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class GloballySearchable implements ResourceAttribute
{
    public function __construct(
        public bool $searchable = true,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->globallySearchable($this->searchable);
    }
}
