<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * Show the action as a toolbar button, outside the Actions menu.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Pinned implements ActionAttribute
{
    public function apply(Action $action): void
    {
        $action->pinned();
    }
}
