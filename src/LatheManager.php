<?php

namespace Lathe;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lathe\Assets\AssetManifest;
use Lathe\Auth\FortifyConfig;
use Lathe\Auth\PanelGate;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Plugins\Plugin;
use Lathe\Plugins\PluginRegistry;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\ConfigStore;

/**
 * What the Lathe facade answers. Holds no state of its own: each accessor resolves a binding.
 */
class LatheManager
{
    public function __construct(protected Container $container)
    {
        //
    }

    public function config(): ConfigStore
    {
        return $this->container->make(ConfigStore::class);
    }

    public function resources(): ResourceRegistry
    {
        return $this->container->make(ResourceRegistry::class);
    }

    public function plugins(): PluginRegistry
    {
        return $this->container->make(PluginRegistry::class);
    }

    public function dashboards(): DashboardRegistry
    {
        return $this->container->make(DashboardRegistry::class);
    }

    public function assets(): AssetManifest
    {
        return $this->container->make(AssetManifest::class);
    }

    public function fortify(): FortifyConfig
    {
        return $this->container->make(FortifyConfig::class);
    }

    /**
     * @param  class-string<Resources\Resource>  ...$classes
     */
    public function registerResources(string ...$classes): void
    {
        $this->resources()->register(...$classes);
    }

    public function registerPlugins(Plugin ...$plugins): void
    {
        $this->plugins()->register(...$plugins);
    }

    public function discoverResources(string $directory, ?string $namespace = null): void
    {
        $this->resources()->discover($directory, $namespace);
    }

    /**
     * Decide panel access with a callback instead of the viewLathe gate.
     *
     * @param  Closure(Request): mixed  $callback
     */
    public function auth(Closure $callback): static
    {
        $this->container->make(PanelGate::class)->using($callback);

        return $this;
    }

    /**
     * Whether the request's user on the panel guard may open the panel.
     */
    public function check(Request $request): bool
    {
        return $this->container->make(PanelGate::class)->check($request);
    }

    public function name(): string
    {
        return $this->config()->string('name');
    }

    public function version(): string
    {
        return InstalledVersions::isInstalled('lathe/lathe')
            ? (InstalledVersions::getPrettyVersion('lathe/lathe') ?? 'dev')
            : 'dev';
    }

    public function path(): string
    {
        return $this->config()->string('path');
    }

    public function loginPath(): string
    {
        return rtrim($this->path(), '/').'/login';
    }
}
