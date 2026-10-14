<?php

namespace Lathe\Http\Controllers\Plugins;

use Lathe\Assets\AssetRegistry;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Plugins\ResourcePluginRegistry;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves registered plugin scripts and styles by name and content hash; nothing else resolves.
 * A resource plugin script is not registered: its URL names the Resource, which alone is built.
 */
class ModuleController
{
    public function __invoke(LatheRequest $request, AssetRegistry $assets, ResourcePluginRegistry $plugins, string $file, string $type = 'scripts'): BinaryFileResponse
    {
        $path = $assets->resolve($type, $file);
        $resource = $request->query('resource');

        if ($path === null && $type === 'scripts' && is_string($resource)) {
            $path = $plugins->script($resource, $file, $request);
        }

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => $type === 'styles' ? 'text/css; charset=utf-8' : 'text/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
