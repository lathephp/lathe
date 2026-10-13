<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;
use Lathe\Search\SearchableColumn;

/**
 * The columns the search box searches, with their strategies.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Search implements ResourceAttribute
{
    public function __construct(
        /** @var list<string|SearchableColumn> */
        public array $columns,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->searchable($this->columns);
    }
}
