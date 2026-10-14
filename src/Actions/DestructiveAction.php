<?php

namespace Lathe\Actions;

/**
 * An action that destroys data: it asks for confirmation, shows a red confirm button and
 * also needs the Resource's destructive action ability.
 */
class DestructiveAction extends Action
{
    public bool $destructive = true;

    protected bool $confirmable = true;
}
