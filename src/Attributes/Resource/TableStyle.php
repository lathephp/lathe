<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Enums\TableStyle as TableStyleEnum;
use Lathe\Resources\ResourceDefinition;

/**
 * The table's row style.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class TableStyle implements ResourceAttribute
{
    public function __construct(
        public TableStyleEnum $style,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->tableStyle($this->style);
    }
}
