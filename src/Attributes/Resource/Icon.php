<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * The sidebar icon, a Lucide component name.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Icon implements ResourceAttribute
{
    public function __construct(
        public string $icon,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->icon($this->icon);
    }
}
