<?php

namespace Lathe\Attributes\Lens;

use Attribute;

/**
 * Show the polling toggle in the lens toolbar.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ShowPollingToggle
{
    public function __construct(
        public bool $show = true,
    ) {
        //
    }
}
