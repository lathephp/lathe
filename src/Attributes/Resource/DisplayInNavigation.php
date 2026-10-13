<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Show the Resource in the sidebar.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class DisplayInNavigation implements ResourceAttribute
{
    public function __construct(
        public bool $display = true,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->displayInNavigation($this->display);
    }
}
