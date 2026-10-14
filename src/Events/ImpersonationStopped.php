<?php

namespace Lathe\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Fires when a user stops acting as another user.
 */
class ImpersonationStopped
{
    public function __construct(public Model $impersonator, public Model $impersonated, public string $redirectTo)
    {
        //
    }
}
