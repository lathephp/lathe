<?php

namespace Lathe\Attributes\Lens;

use Attribute;

/**
 * Refresh the lens every so many seconds.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Polling
{
    public function __construct(
        public int $seconds = 15,
    ) {
        //
    }
}
