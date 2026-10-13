<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Rows per Resource in global search.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class GlobalSearchResults implements ResourceAttribute
{
    public function __construct(
        public int $results,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->globalSearchResults($this->results);
    }
}
