<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lathe\Lathe;
use Lathe\Plugins\PluginRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Once the user is known: sets the request locale and runs the serving() hook of each plugin the request is authorized for.
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
        // The user is known here, so the callback can read it; validation messages follow the locale.
        app()->setLocale(Lathe::resolveUserLocale($request));

        // Only for a user who may open the panel; guest pages and refused users run no plugin code.
        if (Lathe::check($request)) {
            $this->plugins->serve($request);
        }

        return $next($request);
    }
}
