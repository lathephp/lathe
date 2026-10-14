<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * The action modal's max width, sm to 7xl.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ModalSize implements ActionAttribute
{
    public function __construct(
        public string $size,
    ) {
        //
    }

    public function apply(Action $action): void
    {
        $action->size($this->size);
    }
}
