<?php

namespace Lathe\Http\Controllers\Plugins;

use Illuminate\Http\Request;
use Lathe\Plugins\PluginRegistry;
use Symfony\Component\HttpFoundation\Response;

class PluginApiController
{
    public function __invoke(Request $request, PluginRegistry $plugins, string $plugin, string $path = ''): Response
    {
        $found = $plugins->find($plugin);

        abort_if($found === null, 404);
        abort_unless($found->authorize($request), 403);

        return $found->handleRequest($request, $path);
    }
}
