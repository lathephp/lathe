<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * The attribute that titles a record.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Title implements ResourceAttribute
{
    public function __construct(
        public string $attribute,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->title($this->attribute);
    }
}
