<?php

namespace Lathe\Attributes\Dashboard;

use Attribute;
use Lathe\Enums\Icon as IconName;

/**
 * The dashboard's sidebar and title icon.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Icon
{
    public function __construct(
        public IconName $icon,
    ) {
        //
    }
}
