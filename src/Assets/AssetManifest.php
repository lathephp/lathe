<?php

namespace Lathe\Assets;

/**
 * The prebuilt panel assets and their content hash.
 */
class AssetManifest
{
    protected ?string $distPath = null;

    /**
     * The content hash of the built entry file, or "dev" before the first build.
     */
    public function hash(): string
    {
        return 'dev';
    }

    /**
     * The folder pnpm build writes.
     */
    public function distPath(): string
    {
        return $this->distPath ?? dirname(__DIR__, 2).'/dist';
    }

    public function useDistPath(string $path): void
    {
        $this->distPath = $path;
    }
}
