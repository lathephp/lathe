<?php

namespace Lathe\Assets;

/**
 * The prebuilt panel assets and their content hash.
 */
class AssetManifest
{
    /**
     * The content hash of the built entry file, or "dev" before the first build.
     */
    public function hash(): string
    {
        return 'dev';
    }
}
