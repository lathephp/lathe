<?php

namespace Lathe\Attributes\Widget;

use Attribute;

/**
 * Refetches the value when the index filters change, like refreshWhenFiltersChange().
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class RefreshWhenFiltersChange
{
    //
}
