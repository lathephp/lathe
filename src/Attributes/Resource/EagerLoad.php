<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * Relations always eager-loaded.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class EagerLoad implements ResourceAttribute
{
    public function __construct(
        /** @var list<string> */
        public array $relations,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->with($this->relations);
    }
}
