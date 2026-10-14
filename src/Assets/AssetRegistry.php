<?php

namespace Lathe\Assets;

use Illuminate\Contracts\Container\Container;
use Lathe\Exceptions\LatheException;
use Lathe\Http\Requests\LatheRequest;

/**
 * Plugin scripts and styles. The registry is the allow-list: only its entries are served.
 * An entry is a file path, or an https:// URL for a remote asset.
 */
class AssetRegistry
{
    /** The assets registered while a panel request is served; they last for that request only. */
    public const string REQUEST_BAG = 'lathe.assets.request';

    /** Where lathe:assets copies the files, under the public path. */
    public const string PUBLIC_DIRECTORY = 'vendor/lathe/plugins';

    /** @var array<string, string> */
    protected array $scripts = [];

    /** @var array<string, string> */
    protected array $styles = [];

    public function __construct(protected Container $container)
    {
        //
    }

    public function script(string $name, string $path): void
    {
        $this->add('scripts', $name, $path);
    }

    public function style(string $name, string $path): void
    {
        $this->add('styles', $name, $path);
    }

    public function remoteScript(string $name, string $url): void
    {
        $this->add('scripts', $name, $this->https($url));
    }

    public function remoteStyle(string $name, string $url): void
    {
        $this->add('styles', $name, $this->https($url));
    }

    /**
     * @param  array{scripts?: array<string, string>, styles?: array<string, string>}  $assets
     */
    public function register(array $assets): void
    {
        foreach ($assets['scripts'] ?? [] as $name => $path) {
            $this->script($name, $path);
        }

        foreach ($assets['styles'] ?? [] as $name => $path) {
            $this->style($name, $path);
        }
    }

    /**
     * @return array<string, string>
     */
    public function scripts(): array
    {
        return $this->entries('scripts');
    }

    /**
     * @return array<string, string>
     */
    public function styles(): array
    {
        return $this->entries('styles');
    }

    /**
     * The file a request name such as heartbeat-3f9a1c2e.js stands for, if it is registered,
     * or, when given, if it is one of the entries.
     *
     * @param  array<string, string>|null  $entries
     */
    public function resolve(string $type, string $file, ?array $entries = null): ?string
    {
        $extension = $type === 'styles' ? 'css' : 'js';

        if (preg_match('/^(?<name>[a-z0-9-]+)-(?<hash>[0-9a-f]{8})\.'.$extension.'$/', $file, $match) !== 1) {
            return null;
        }

        $path = ($entries ?? $this->entries($type === 'styles' ? 'styles' : 'scripts'))[$match['name']] ?? null;

        return $path !== null && ! $this->isRemote($path) && is_file($path) && $this->hash($path) === $match['hash'] ? $path : null;
    }

    /**
     * The URL of a registered script: its remote URL, the copy lathe:assets made, or the hashed panel URL.
     */
    public function scriptUrl(string $prefix, string $name): ?string
    {
        return $this->url('scripts', $prefix, $name);
    }

    public function styleUrl(string $prefix, string $name): ?string
    {
        return $this->url('styles', $prefix, $name);
    }

    /**
     * The URL of a resource plugin script, which is not registered. The panel URL names the
     * Resource, so the module route builds only that Resource to find the file.
     */
    public function resourceScriptUrl(string $prefix, string $name, string $path, string $resource): string
    {
        $url = $this->sourceUrl('scripts', $prefix, $name, $path);

        return str_starts_with($url, "{$prefix}/modules/") ? $url.'?resource='.rawurlencode($resource) : $url;
    }

    /**
     * @return list<string>
     */
    public function styleUrls(string $prefix): array
    {
        return array_values(array_filter(array_map(
            fn (string $name) => $this->styleUrl($prefix, $name),
            array_keys($this->styles()),
        )));
    }

    public function isRemote(string $entry): bool
    {
        return str_starts_with($entry, 'https://');
    }

    /**
     * Where lathe:assets puts the copy of a registered file.
     */
    public function copyPath(string $type, string $name): string
    {
        return public_path(self::PUBLIC_DIRECTORY.'/'.$name.'.'.$this->extension($type));
    }

    /**
     * The origins of the remote assets, for the script-src and style-src of the response.
     *
     * @return array{script: list<string>, style: list<string>}
     */
    public function remoteOrigins(): array
    {
        return [
            'script' => $this->origins($this->scripts()),
            'style' => $this->origins($this->styles()),
        ];
    }

    /**
     * The policy with the remote origins added to its script-src and style-src. A directive the
     * policy leaves out falls back to default-src, so it is written out with the origins added.
     */
    public function extendPolicy(string $policy): string
    {
        $directives = array_values(array_filter(array_map('trim', explode(';', $policy))));

        foreach ($this->remoteOrigins() as $kind => $origins) {
            if ($origins === []) {
                continue;
            }

            $name = "{$kind}-src";
            $index = $this->directiveIndex($directives, $name);

            if ($index === null) {
                $default = $this->directiveIndex($directives, 'default-src');

                if ($default === null) {
                    continue;
                }

                $directives[] = $name.substr($directives[$default], strlen('default-src'));
                $index = count($directives) - 1;
            }

            $present = explode(' ', $directives[$index]);
            $directives[$index] .= ' '.implode(' ', array_diff($origins, $present));
            $directives[$index] = rtrim($directives[$index]);
        }

        return implode('; ', $directives);
    }

    public function hash(string $path): string
    {
        return substr((string) hash_file('sha256', $path), 0, 8);
    }

    protected function add(string $type, string $name, string $entry): void
    {
        if (preg_match('#^([a-z][a-z0-9+.-]+:|//)#i', $entry) === 1) {
            $entry = $this->https($entry);
        }

        // A request-bound registration must not outlive its request in a long-lived worker.
        if ($this->container->bound(LatheRequest::class)) {
            $bag = $this->requestBag();
            $bag[$type][$name] = $entry;
            $this->container->instance(self::REQUEST_BAG, $bag);

            return;
        }

        if ($type === 'styles') {
            $this->styles[$name] = $entry;
        } else {
            $this->scripts[$name] = $entry;
        }
    }

    protected function https(string $url): string
    {
        if (! str_starts_with($url, 'https://')) {
            throw new LatheException("A remote asset must be an https:// URL, got \"{$url}\".");
        }

        return $url;
    }

    /**
     * @return array<string, string>
     */
    protected function entries(string $type): array
    {
        $bag = $this->requestBag();

        return $type === 'styles' ? [...$this->styles, ...$bag['styles']] : [...$this->scripts, ...$bag['scripts']];
    }

    /**
     * @return array{scripts: array<string, string>, styles: array<string, string>}
     */
    protected function requestBag(): array
    {
        $bag = $this->container->bound(self::REQUEST_BAG) ? $this->container->make(self::REQUEST_BAG) : null;

        /** @var array{scripts: array<string, string>, styles: array<string, string>} */
        return is_array($bag) ? $bag : ['scripts' => [], 'styles' => []];
    }

    protected function url(string $type, string $prefix, string $name): ?string
    {
        $source = $this->entries($type)[$name] ?? null;

        return $source === null ? null : $this->sourceUrl($type, $prefix, $name, $source);
    }

    protected function sourceUrl(string $type, string $prefix, string $name, string $source): string
    {
        if ($this->isRemote($source)) {
            return $source;
        }

        $copy = $this->copyPath($type, $name);

        if (is_file($copy)) {
            return '/'.self::PUBLIC_DIRECTORY.'/'.$name.'.'.$this->extension($type).'?v='.$this->hash($copy);
        }

        // A missing file keeps its place with a hash that never resolves, so the panel tries
        // the module, names the plugin that failed to load and keeps working.
        $hash = is_file($source) ? $this->hash($source) : '00000000';
        $folder = $type === 'styles' ? 'styles' : 'modules';

        return "{$prefix}/{$folder}/{$name}-{$hash}.{$this->extension($type)}";
    }

    protected function extension(string $type): string
    {
        return $type === 'styles' ? 'css' : 'js';
    }

    /**
     * @param  array<string, string>  $entries
     * @return list<string>
     */
    protected function origins(array $entries): array
    {
        $origins = [];

        foreach ($entries as $entry) {
            $parts = $this->isRemote($entry) ? parse_url($entry) : false;

            if (is_array($parts) && isset($parts['host'])) {
                $origins[] = 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * @param  array<int, string>  $directives
     */
    protected function directiveIndex(array $directives, string $name): ?int
    {
        foreach ($directives as $index => $directive) {
            if (strcasecmp(strtok($directive, ' ') ?: '', $name) === 0) {
                return $index;
            }
        }

        return null;
    }
}
