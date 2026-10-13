<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * The attribute shown under the title.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Subtitle implements ResourceAttribute
{
    public function __construct(
        public string $attribute,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->subtitle($this->attribute);
    }
}
