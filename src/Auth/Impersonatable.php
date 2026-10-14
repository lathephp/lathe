<?php

namespace Lathe\Auth;

/**
 * Lets the panel impersonate users of this model. Nobody may impersonate until the app
 * overrides canImpersonate(), so adding the trait alone opens nothing.
 */
trait Impersonatable
{
    public function canImpersonate(): bool
    {
        return false;
    }

    public function canBeImpersonated(): bool
    {
        return true;
    }
}
