<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * Write no audit rows when the action runs.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class WithoutActionEvents implements ActionAttribute
{
    public function apply(Action $action): void
    {
        $action->withoutActionEvents();
    }
}
