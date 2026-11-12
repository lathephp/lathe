<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses the password and two-factor routes with 403 while an admin impersonates someone, so an
 * impersonation never reads or changes the target's account security.
 *
 * @internal
 */
class RefuseWhileImpersonating
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Lathe::impersonator()->impersonating($request), 403, Lathe::__('Security settings are not available while impersonating.'));

        return $next($request);
    }
}
