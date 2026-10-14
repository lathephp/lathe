<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * Ask for confirmation before the action runs; ":count" in the text is the selection size.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Confirmable implements ActionAttribute
{
    public function __construct(
        public string $text = '',
    ) {
        //
    }

    public function apply(Action $action): void
    {
        $action->confirmable($this->text);
    }
}
