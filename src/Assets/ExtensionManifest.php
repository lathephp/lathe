<?php

namespace Lathe\Assets;

/**
 * The app's own extensions, built by lathe:build into public/vendor/lathe/extensions. While
 * lathe:dev runs, its hot file holds the dev server URL of the boot module instead.
 */
class ExtensionManifest
{
    /** Where lathe:build writes the modules and manifest.json, under the public path. */
    public const string PUBLIC_DIRECTORY = 'vendor/lathe/extensions';

    /**
     * The modules the page loads for the app's extensions.
     *
     * @return list<string>
     */
    public function moduleUrls(): array
    {
        $hot = $this->path('hot');

        if (is_file($hot)) {
            return [trim((string) file_get_contents($hot))];
        }

        $entry = $this->manifest()['entry'] ?? null;

        return is_string($entry) ? ['/'.self::PUBLIC_DIRECTORY.'/'.$entry] : [];
    }

    /**
     * The component keys the built extensions register, by kind.
     *
     * @return array<string, list<string>>
     */
    public function components(): array
    {
        $components = $this->manifest()['components'] ?? null;

        /** @var array<string, list<string>> */
        return is_array($components) ? $components : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function manifest(): array
    {
        $file = $this->path('manifest.json');
        $manifest = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        /** @var array<string, mixed> */
        return is_array($manifest) ? $manifest : [];
    }

    protected function path(string $file): string
    {
        return public_path(self::PUBLIC_DIRECTORY.'/'.$file);
    }
}
