<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lathe\Plugins\PluginRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the serving() hook of each plugin the request is authorized for.
 */
class ServePlugins
{
    public function __construct(protected PluginRegistry $plugins)
    {
        //
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->plugins->serve($request);

        return $next($request);
    }
}
