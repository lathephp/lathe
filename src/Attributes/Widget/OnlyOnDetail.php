<?php

namespace Lathe\Attributes\Widget;

use Attribute;

/**
 * Shows the widget on the detail page only, like onlyOnDetail().
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class OnlyOnDetail
{
    //
}
