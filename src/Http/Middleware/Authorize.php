<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a signed-in user who fails Lathe::check() with 403. The one way through is stopping an
 * impersonation: an admin who impersonates someone the panel refuses can still get back.
 */
class Authorize
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Lathe::check($request) || $this->stopsImpersonating($request)) {
            return $next($request);
        }

        if (Authenticate::answersJson($request)) {
            return response()->json(['message' => Lathe::__('This action is unauthorized.')], 403);
        }

        abort(403);
    }

    private function stopsImpersonating(Request $request): bool
    {
        return $request->routeIs('lathe.api.impersonate.destroy')
            && Lathe::impersonator()->impersonating($request);
    }
}
