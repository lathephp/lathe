<?php

namespace Lathe\Http\Controllers\Plugins;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lathe\Plugins\PluginRegistry;

class PluginPageController
{
    public function __invoke(Request $request, PluginRegistry $plugins, string $plugin, string $path = ''): Response
    {
        $found = $plugins->find($plugin);

        abort_if($found === null, 404);
        abort_unless($found->authorize($request), 403);
        // The settings path belongs to the settings form, which a plugin without settings() lacks.
        abort_if($path === 'settings' && $found->settings() === null, 404);

        return Inertia::render('Plugin', [
            'plugin' => $found::uriKey(),
            'title' => $found->name(),
            'path' => $path,
        ]);
    }
}
