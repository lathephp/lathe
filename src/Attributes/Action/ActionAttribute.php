<?php

namespace Lathe\Attributes\Action;

use Lathe\Actions\Action;

/**
 * A class attribute that sets one option of an Action. The constructor applies it, so a
 * fluent call made after construction wins.
 */
interface ActionAttribute
{
    public function apply(Action $action): void;
}
