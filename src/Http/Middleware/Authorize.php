<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lathe\Lathe;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a signed-in user who fails Lathe::check() with 403.
 */
class Authorize
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Lathe::check($request)) {
            return $next($request);
        }

        if (Authenticate::answersJson($request)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        abort(403);
    }
}
