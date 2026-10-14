<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * Show the action in each table row's menu.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ShowInline implements ActionAttribute
{
    public function apply(Action $action): void
    {
        $action->showInline();
    }
}
