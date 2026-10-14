<?php

namespace Lathe\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Fires after an action ran on its models: once for an inline run, once per chunk when queued.
 */
class ActionExecuted
{
    /**
     * @param  object|string  $action  the Action, or its name
     * @param  Collection<int, Model>  $models
     */
    public function __construct(public object|string $action, public Collection $models)
    {
        //
    }
}
