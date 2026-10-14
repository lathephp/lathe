<?php

namespace Lathe\Plugins;

use Lathe\Assets\AssetRegistry;
use Lathe\Exceptions\DuplicateResourcePluginException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceRegistry;

/**
 * The resource plugins placed in Resource::fields(), indexed by component key. The scan
 * runs once per process, registers each plugin's assets and refuses a key two plugins share.
 */
class ResourcePluginRegistry
{
    /** @var array<string, array{plugin: class-string<ResourcePlugin>, resources: list<class-string<resource>>}>|null */
    protected ?array $index = null;

    public function __construct(protected ResourceRegistry $resources, protected AssetRegistry $assets)
    {
        //
    }

    /**
     * The resource plugins in a Resource's detail items, in declaration order.
     *
     * @param  class-string<resource>  $class
     * @return list<ResourcePlugin>
     */
    public static function placedIn(string $class, LatheRequest $request): array
    {
        $resource = $class::make();
        $items = $resource->fieldsForDetail($request) ?: $resource->fields($request);

        return array_values(array_filter($items, fn (mixed $item) => $item instanceof ResourcePlugin));
    }

    /**
     * Scan every Resource, register the assets and index the components. A later call does nothing.
     */
    public function boot(?LatheRequest $request = null): void
    {
        $this->index($request);
    }

    /**
     * The Resource with this URI key that places the plugin with this component key.
     *
     * @return class-string<resource>|null
     */
    public function resourceFor(string $component, string $uriKey): ?string
    {
        foreach ($this->index()[$component]['resources'] ?? [] as $class) {
            if ($class::uriKey() === $uriKey) {
                return $class;
            }
        }

        return null;
    }

    public function flush(): void
    {
        $this->index = null;
    }

    /**
     * Each component with the plugin class that owns the key and the Resources that place it.
     * A Resource may share a plugin class with another, but not a key with a different class.
     *
     * @return array<string, array{plugin: class-string<ResourcePlugin>, resources: list<class-string<resource>>}>
     */
    protected function index(?LatheRequest $request = null): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $request ??= app(LatheRequest::class);
        $index = [];

        foreach ($this->resources->all() as $class) {
            foreach (static::placedIn($class, $request) as $plugin) {
                $component = $plugin->component();
                $known = $index[$component] ?? null;

                if ($known !== null && $known['plugin'] !== $plugin::class) {
                    throw DuplicateResourcePluginException::forComponent($component, $known['plugin'], $plugin::class);
                }

                if ($known !== null && in_array($class, $known['resources'], true)) {
                    throw DuplicateResourcePluginException::twiceIn($component, $class);
                }

                $index[$component] = ['plugin' => $plugin::class, 'resources' => [...($known['resources'] ?? []), $class]];

                foreach ($plugin->assets()['scripts'] ?? [] as $name => $path) {
                    $this->assets->script($name, $path);
                }

                foreach ($plugin->assets()['styles'] ?? [] as $name => $path) {
                    $this->assets->style($name, $path);
                }
            }
        }

        return $this->index = $index;
    }
}
