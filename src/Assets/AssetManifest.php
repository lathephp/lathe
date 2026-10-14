<?php

namespace Lathe\Assets;

/**
 * The prebuilt panel assets: the Vite manifest in dist/, the asset hash and the import map.
 */
class AssetManifest
{
    /** The bare specifiers the import map serves, one build entry each. */
    public const array SPECIFIERS = [
        'react', 'react/jsx-runtime', 'react/compiler-runtime', 'react-dom', 'react-dom/client',
        '@lathephp/core', '@lathephp/ui',
    ];

    public const string ENTRY = 'resources/js/app.tsx';

    protected ?string $distPath = null;

    /** @var array<string, array{file?: string, css?: list<string>}>|null */
    protected ?array $manifest = null;

    /**
     * The content hash in the entry file's name, or "dev" before the first build.
     */
    public function hash(): string
    {
        $file = $this->manifest()[self::ENTRY]['file'] ?? null;

        if ($file === null || preg_match('/-([0-9a-z]{8})\.js$/', $file, $match) !== 1) {
            return 'dev';
        }

        return $match[1];
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
        $this->manifest = null;
    }

    /**
     * The built file a request names, if the manifest lists it. Names are matched,
     * never joined to a path.
     */
    public function file(string $hash, string $name): ?string
    {
        if ($hash !== $this->hash()) {
            return null;
        }

        foreach ($this->listedFiles() as $file) {
            if (basename($file) === $name) {
                return $this->distPath().'/'.$file;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> specifier => URL
     */
    public function importMap(string $prefix): array
    {
        $map = [];

        foreach (self::SPECIFIERS as $specifier) {
            $file = $this->manifest()["lathe-vendor:{$specifier}"]['file'] ?? null;

            if ($file !== null) {
                $map[$specifier] = $this->url($prefix, $file);
            }
        }

        return $map;
    }

    public function entryUrl(string $prefix): ?string
    {
        $file = $this->manifest()[self::ENTRY]['file'] ?? null;

        return $file === null ? null : $this->url($prefix, $file);
    }

    /**
     * @return list<string>
     */
    public function stylesheetUrls(string $prefix): array
    {
        return array_map(fn (string $file) => $this->url($prefix, $file), $this->manifest()[self::ENTRY]['css'] ?? []);
    }

    /**
     * The stylesheet of one theme style, such as nova. Each style builds on its own.
     */
    public function styleUrl(string $prefix, string $style): ?string
    {
        $file = $this->manifest()["resources/css/theme-{$style}.css"]['file'] ?? null;

        return $file === null ? null : $this->url($prefix, $file);
    }

    protected function url(string $prefix, string $file): string
    {
        return rtrim($prefix, '/').'/assets/'.$this->hash().'/'.basename($file);
    }

    /**
     * @return list<string>
     */
    protected function listedFiles(): array
    {
        $files = [];

        foreach ($this->manifest() as $entry) {
            if (isset($entry['file'])) {
                $files[] = $entry['file'];
            }

            foreach ($entry['css'] ?? [] as $css) {
                $files[] = $css;
            }
        }

        return $files;
    }

    /**
     * @return array<string, array{file?: string, css?: list<string>}>
     */
    protected function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $path = $this->distPath().'/manifest.json';
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        /** @var array<string, array{file?: string, css?: list<string>}> $manifest */
        $manifest = is_array($decoded) ? $decoded : [];

        return $this->manifest = $manifest;
    }
}
