<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * The sidebar label.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class NavigationLabel implements ResourceAttribute
{
    public function __construct(
        public string $label,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->navigationLabel($this->label);
    }
}
