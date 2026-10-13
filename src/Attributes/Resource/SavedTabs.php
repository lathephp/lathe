<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Let users save views as tabs.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class SavedTabs implements ResourceAttribute
{
    public function __construct(
        public bool $enabled = true,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->savedTabs($this->enabled);
    }
}
