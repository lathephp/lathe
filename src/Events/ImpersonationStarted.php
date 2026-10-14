<?php

namespace Lathe\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Fires when a user starts acting as another user.
 */
class ImpersonationStarted
{
    public function __construct(public Model $impersonator, public Model $impersonated)
    {
        //
    }
}
