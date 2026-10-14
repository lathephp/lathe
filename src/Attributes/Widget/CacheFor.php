<?php

namespace Lathe\Attributes\Widget;

use Attribute;

/**
 * How many seconds the result stays cached, like a cacheFor() override.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class CacheFor
{
    public function __construct(
        public int $seconds,
    ) {
        //
    }
}
