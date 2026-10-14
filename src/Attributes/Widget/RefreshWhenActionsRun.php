<?php

namespace Lathe\Attributes\Widget;

use Attribute;

/**
 * Refetches the value after an action runs, like refreshWhenActionsRun().
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class RefreshWhenActionsRun
{
    //
}
