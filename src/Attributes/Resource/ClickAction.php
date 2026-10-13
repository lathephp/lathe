<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Enums\ClickAction as ClickActionEnum;
use Lathe\Resources\ResourceDefinition;

/**
 * What a row click does.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ClickAction implements ResourceAttribute
{
    public function __construct(
        public ClickActionEnum $action,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->clickAction($this->action);
    }
}
