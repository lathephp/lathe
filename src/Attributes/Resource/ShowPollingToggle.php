<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Show the polling toggle in the toolbar.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ShowPollingToggle implements ResourceAttribute
{
    public function __construct(
        public bool $show = true,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->showPollingToggle($this->show);
    }
}
