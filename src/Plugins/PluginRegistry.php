<?php

namespace Lathe\Plugins;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Lathe\Assets\AssetRegistry;
use Lathe\Exceptions\LatheException;
use Lathe\Exceptions\PluginDependencyException;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Support\ConfigStore;

/**
 * The plugins registered with the panel, by class. A repeat registration is a no-op.
 */
class PluginRegistry
{
    /** @var array<string, Plugin> */
    protected array $plugins = [];

    public function __construct(protected AssetRegistry $assets, protected ConfigStore $config)
    {
        //
    }

    public function register(Plugin ...$plugins): void
    {
        foreach ($plugins as $plugin) {
            if ($this->registered($plugin::class)) {
                continue;
            }

            $this->checkRequirements($plugin);
            $key = $plugin::uriKey();

            if (isset($this->plugins[$key])) {
                throw new LatheException(sprintf(
                    'Plugins %s and %s share the key "%s". Give one of them a distinct uriKey().',
                    $this->plugins[$key]::class, $plugin::class, $key,
                ));
            }

            $this->plugins[$key] = $plugin;

            foreach ($plugin->assets()['scripts'] ?? [] as $name => $path) {
                $this->assets->script($name, $path);
            }

            foreach ($plugin->assets()['styles'] ?? [] as $name => $path) {
                $this->assets->style($name, $path);
            }

            foreach ($plugin->middleware() as $middleware) {
                app(Router::class)->pushMiddlewareToGroup('lathe', $middleware);
            }

            $plugin->register();
            $plugin->boot();
        }
    }

    /**
     * @return list<Plugin>
     */
    public function all(): array
    {
        return array_values($this->plugins);
    }

    public function find(string $uriKey): ?Plugin
    {
        return $this->plugins[$uriKey] ?? null;
    }

    /**
     * @return list<Plugin>
     */
    public function authorized(Request $request): array
    {
        return array_values(array_filter($this->plugins, fn (Plugin $plugin) => $plugin->authorize($request)));
    }

    /**
     * Run serving() for every plugin the request is authorized for.
     */
    public function serve(Request $request): void
    {
        foreach ($this->authorized($request) as $plugin) {
            $plugin->serving($request);
        }
    }

    /**
     * The sidebar sections of the plugins the request is authorized for.
     *
     * @return list<MenuSection>
     */
    public function menus(Request $request): array
    {
        $sections = [];

        foreach ($this->authorized($request) as $plugin) {
            $menu = $plugin->menu($request);

            if ($menu === null && $plugin->settings() !== null) {
                $menu = [MenuSection::section($plugin->name(), [MenuItem::link('Settings', 'plugins/'.$plugin::uriKey().'/settings')])];
            }

            foreach ($menu ?? [] as $section) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * URLs of every plugin script, for the import list and modulepreload links.
     *
     * @return list<string>
     */
    public function moduleUrls(): array
    {
        $prefix = '/'.trim($this->config->string('path'), '/');

        return array_values(array_filter(array_map(
            fn (string $name) => $this->assets->scriptUrl($prefix, $name),
            array_keys($this->assets->scripts()),
        )));
    }

    /**
     * @param  class-string<Plugin>  $class
     */
    protected function registered(string $class): bool
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin::class === $class) {
                return true;
            }
        }

        return false;
    }

    protected function checkRequirements(Plugin $plugin): void
    {
        $missing = array_values(array_filter(
            $plugin->requires(),
            fn (string $class) => ! class_exists($class) && ! interface_exists($class),
        ));

        if ($missing !== []) {
            throw new PluginDependencyException(sprintf('%s (%s) requires %s, which cannot be found. Install the packages that provide them.', $plugin->name(), $plugin::class, implode(', ', $missing)));
        }
    }
}
