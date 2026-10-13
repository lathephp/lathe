<?php

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripes the tables for one request that sends `X-Bench-Striped: 1`, so the Octane
 * lane can check that a per-request config change does not reach the next request.
 */
class StripedTables
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Bench-Striped') !== '1') {
            return $next($request);
        }

        $before = config('lathe.table.striped');
        config(['lathe.table.striped' => true]);

        try {
            return $next($request);
        } finally {
            config(['lathe.table.striped' => $before]);
        }
    }
}
