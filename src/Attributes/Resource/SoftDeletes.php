<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Whether the Resource soft-deletes; null detects the trait.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class SoftDeletes implements ResourceAttribute
{
    public function __construct(
        public ?bool $softDeletes = true,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->softDeletes($this->softDeletes);
    }
}
