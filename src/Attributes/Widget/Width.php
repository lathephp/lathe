<?php

namespace Lathe\Attributes\Widget;

use Attribute;
use Lathe\Enums\CardWidth;

/**
 * The card width, like width().
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Width
{
    public function __construct(
        public CardWidth $width,
    ) {
        //
    }
}
