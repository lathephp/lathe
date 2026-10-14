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
    /** @var array<string, class-string<resource>>|null */
    protected ?array $owners = null;

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
        $this->owners($request);
    }

    /**
     * The Resource that places the plugin with this component key.
     *
     * @return class-string<resource>|null
     */
    public function ownerOf(string $component): ?string
    {
        return $this->owners()[$component] ?? null;
    }

    public function flush(): void
    {
        $this->owners = null;
    }

    /**
     * @return array<string, class-string<resource>>
     */
    protected function owners(?LatheRequest $request = null): array
    {
        if ($this->owners !== null) {
            return $this->owners;
        }

        $request ??= app(LatheRequest::class);
        $owners = [];

        foreach ($this->resources->all() as $class) {
            foreach (static::placedIn($class, $request) as $plugin) {
                $component = $plugin->component();

                if (isset($owners[$component])) {
                    throw DuplicateResourcePluginException::forComponent($component, $owners[$component], $class);
                }

                $owners[$component] = $class;

                foreach ($plugin->assets()['scripts'] ?? [] as $name => $path) {
                    $this->assets->script($name, $path);
                }

                foreach ($plugin->assets()['styles'] ?? [] as $name => $path) {
                    $this->assets->style($name, $path);
                }
            }
        }

        return $this->owners = $owners;
    }
}
