<?php

namespace Lathe\Attributes\Widget;

use Attribute;

/**
 * Polls the value every so many seconds, like refreshEvery().
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class RefreshEvery
{
    public function __construct(
        public int $seconds,
    ) {
        //
    }
}
