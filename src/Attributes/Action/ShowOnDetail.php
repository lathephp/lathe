<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * Whether the action shows on the detail page.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ShowOnDetail implements ActionAttribute
{
    public function __construct(
        public bool $show = true,
    ) {
        //
    }

    public function apply(Action $action): void
    {
        if (! $this->show) {
            $action->exceptOnDetail();
        }
    }
}
