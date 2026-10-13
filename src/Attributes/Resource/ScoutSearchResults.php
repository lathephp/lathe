<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * How many keys a Scout search returns.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ScoutSearchResults implements ResourceAttribute
{
    public function __construct(
        public int $results,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->scoutSearchResults($this->results);
    }
}
