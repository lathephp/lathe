<?php

namespace Lathe\Http\Controllers\Assets;

use Illuminate\Http\Request;
use Lathe\Assets\AssetManifest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the prebuilt panel files the manifest lists, cached for good.
 */
class AssetController
{
    public function __invoke(Request $request, AssetManifest $assets, string $hash, string $file): Response
    {
        $path = $assets->file($hash, $file);

        abort_if($path === null || ! is_file($path), 404);

        $etag = '"'.$hash.'-'.substr((string) hash_file('sha256', $path), 0, 16).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => 'public, max-age=31536000, immutable']);
        }

        return new BinaryFileResponse($path, 200, [
            'Content-Type' => match (true) {
                str_ends_with($path, '.css') => 'text/css; charset=utf-8',
                str_ends_with($path, '.woff2') => 'font/woff2',
                default => 'text/javascript; charset=utf-8',
            },
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
        ]);
    }
}
