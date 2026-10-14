<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * Whether the action shows on the index toolbar.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ShowOnIndex implements ActionAttribute
{
    public function __construct(
        public bool $show = true,
    ) {
        //
    }

    public function apply(Action $action): void
    {
        if (! $this->show) {
            $action->exceptOnIndex();
        }
    }
}
