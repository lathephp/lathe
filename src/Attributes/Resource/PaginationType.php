<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Enums\PaginationType as PaginationTypeEnum;
use Lathe\Resources\ResourceDefinition;

/**
 * How the index pages.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class PaginationType implements ResourceAttribute
{
    public function __construct(
        public PaginationTypeEnum $type,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->paginationType($this->type);
    }
}
