<?php

namespace Lathe\Attributes\Resource;

use Attribute;
use Lathe\Resources\ResourceDefinition;

/**
 * The model the Resource wraps.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Model implements ResourceAttribute
{
    public function __construct(
        /** @var class-string<\Illuminate\Database\Eloquent\Model> */
        public string $class,
    ) {
        //
    }

    public function apply(ResourceDefinition $definition): void
    {
        $definition->model($this->class);
    }
}
