<?php

namespace Lathe\Attributes\Action;

use Attribute;
use Lathe\Actions\Action;

/**
 * The action modal's style: "fullscreen", or "window" for anything else.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ModalStyle implements ActionAttribute
{
    public function __construct(
        public string $style,
    ) {
        //
    }

    public function apply(Action $action): void
    {
        $action->fullscreen($this->style === 'fullscreen');
    }
}
