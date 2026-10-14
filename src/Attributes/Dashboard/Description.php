<?php

namespace Lathe\Attributes\Dashboard;

use Attribute;

/**
 * The line under the dashboard title.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Description
{
    public function __construct(
        public string $text,
    ) {
        //
    }
}
