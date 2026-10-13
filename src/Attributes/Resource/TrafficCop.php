<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Refuse an edit of a record that changed since the form loaded.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class TrafficCop implements ResourceAttribute
{
    public function __construct(
        public bool $enabled = true,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->trafficCop($this->enabled);
    }
}
