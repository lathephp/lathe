<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * Run the action once with no selected rows.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Standalone implements ActionAttribute
{
    public function apply(Action $action): void
    {
        $action->standalone();
    }
}
