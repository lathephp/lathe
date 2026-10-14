<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * Style the action as destructive and ask the destructive ability before it runs.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Destructive implements ActionAttribute
{
    public function apply(Action $action): void
    {
        $action->destructive = true;
    }
}
