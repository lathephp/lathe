<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Draw vertical column borders.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ShowColumnBorders implements ResourceAttribute
{
    public function __construct(
        public bool $show = true,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->showColumnBorders($this->show);
    }
}
