<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * No dividers between fields in forms and details.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Borderless implements ResourceAttribute
{
    public function __construct(
        public bool $borderless = true,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->borderless($this->borderless);
    }
}
