<?php

namespace Lathe\Http\Controllers\Plugins;

use Illuminate\Http\Request;
use Lathe\Plugins\PluginRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * The plugin API catch-all, for every verb: `settings` goes to the settings handler and any
 * other path to the plugin's handleRequest().
 */
class PluginController
{
    public function __invoke(Request $request, PluginRegistry $plugins, string $plugin, string $path = ''): Response
    {
        $found = $plugins->find($plugin);

        abort_if($found === null, 404);
        abort_unless($found->authorize($request), 403);

        return $path === 'settings' ? $found->handleSettingsRequest($request) : $found->handleRequest($request, $path);
    }
}
