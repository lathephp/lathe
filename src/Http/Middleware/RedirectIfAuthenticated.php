<?php

namespace Lathe\Http\Middleware;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated as Middleware;
use Illuminate\Http\Request;
use Lathe\Lathe;

/**
 * Sends a signed-in user who opens a guest page of the panel, such as the login page,
 * to the panel instead of the host's own home route.
 */
class RedirectIfAuthenticated extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return Lathe::resolveInitialPath($request);
    }
}
