<?php

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks every panel response with `X-Lathe-Insights: 1`, the InsightsPlugin's middleware() example.
 */
class InsightsHeader
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Lathe-Insights', '1');

        return $response;
    }
}
