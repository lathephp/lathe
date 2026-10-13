<?php

namespace Lathe\Attributes\Resource;

use Lathe\Resources\ResourceDefinition;

/**
 * A class attribute that sets one option of a Resource definition. A fluent call in
 * configure() runs after it and wins.
 */
interface ResourceAttribute
{
    public function apply(ResourceDefinition $definition): void;
}
