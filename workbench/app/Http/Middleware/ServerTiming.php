<?php

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds how long the server took as `Server-Timing: total;dur=<ms>`, so the browser's
 * network panel shows it. Workbench only: the package never sends this header.
 */
class ServerTiming
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);

        $response = $next($request);

        $response->headers->set('Server-Timing', sprintf('total;dur=%.1f', (hrtime(true) - $started) / 1e6));

        return $response;
    }
}
