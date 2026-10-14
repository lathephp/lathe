<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * Run the action on exactly one selected row.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Sole implements ActionAttribute
{
    public function apply(Action $action): void
    {
        $action->sole();
    }
}
