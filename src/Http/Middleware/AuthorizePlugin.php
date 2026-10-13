<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lathe\Plugins\PluginRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a plugin's own routes: 404 for an unknown plugin, 403 when authorize() refuses.
 */
class AuthorizePlugin
{
    public function __construct(protected PluginRegistry $plugins)
    {
        //
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $uriKey): Response
    {
        $plugin = $this->plugins->find($uriKey);

        abort_if($plugin === null, 404);
        abort_unless($plugin->authorize($request), 403);

        return $next($request);
    }
}
