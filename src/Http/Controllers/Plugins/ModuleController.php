<?php

namespace Lathe\Http\Controllers\Plugins;

use Lathe\Assets\AssetRegistry;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves registered plugin scripts and styles by name and content hash; nothing else resolves.
 */
class ModuleController
{
    public function __invoke(AssetRegistry $assets, string $file, string $type = 'scripts'): BinaryFileResponse
    {
        $path = $assets->resolve($type, $file);

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => $type === 'styles' ? 'text/css; charset=utf-8' : 'text/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
