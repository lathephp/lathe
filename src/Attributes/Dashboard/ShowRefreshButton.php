<?php

namespace Lathe\Attributes\Dashboard;

use Attribute;

/**
 * Shows a button that refreshes every card of the dashboard.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ShowRefreshButton
{
    //
}
