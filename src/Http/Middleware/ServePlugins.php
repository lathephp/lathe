<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lathe\Lathe;
use Lathe\Plugins\PluginRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the serving() hook of each plugin the request is authorized for, once the user is known.
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
        // Only for a user who may open the panel; guest pages and refused users run no plugin code.
        if (Lathe::check($request)) {
            $this->plugins->serve($request);
        }

        return $next($request);
    }
}
