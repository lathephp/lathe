<?php

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the worker's memory in bytes and its process id, so the Octane lane can tell
 * whether a long-lived worker grows. Only scripts/octane-lane.sh turns it on.
 */
class WorkerMemory
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Worker-Memory', (string) memory_get_usage());
        $response->headers->set('X-Worker-Pid', (string) getmypid());

        return $response;
    }
}
