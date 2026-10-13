<?php

namespace Lathe\Assets;

/**
 * Plugin scripts and styles. The registry is the allow-list: only its entries are served.
 */
class AssetRegistry
{
    /** @var array<string, string> */
    protected array $scripts = [];

    /** @var array<string, string> */
    protected array $styles = [];

    public function script(string $name, string $path): void
    {
        $this->scripts[$name] = $path;
    }

    public function style(string $name, string $path): void
    {
        $this->styles[$name] = $path;
    }

    /**
     * @return array<string, string>
     */
    public function scripts(): array
    {
        return $this->scripts;
    }

    /**
     * The file a request name such as heartbeat-3f9a1c2e.js stands for, if it is registered.
     */
    public function resolve(string $type, string $file): ?string
    {
        $entries = $type === 'styles' ? $this->styles : $this->scripts;
        $extension = $type === 'styles' ? 'css' : 'js';

        if (preg_match('/^(?<name>[a-z0-9-]+)-(?<hash>[0-9a-f]{8})\.'.$extension.'$/', $file, $match) !== 1) {
            return null;
        }

        $path = $entries[$match['name']] ?? null;

        return $path !== null && is_file($path) && $this->hash($path) === $match['hash'] ? $path : null;
    }

    /**
     * The URL of a registered script, carrying the 8-character content hash.
     */
    public function scriptUrl(string $prefix, string $name): ?string
    {
        $path = $this->scripts[$name] ?? null;

        return $path !== null && is_file($path) ? "{$prefix}/modules/{$name}-{$this->hash($path)}.js" : null;
    }

    public function hash(string $path): string
    {
        return substr((string) hash_file('sha256', $path), 0, 8);
    }
}
