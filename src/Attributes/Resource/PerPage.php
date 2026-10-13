<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Page size options; the first is the default.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class PerPage implements ResourceAttribute
{
    public function __construct(
        /** @var list<int> */
        public array $options,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->perPage($this->options);
    }
}
