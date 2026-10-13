<?php

namespace Lathe;

use Composer\InstalledVersions;
use Illuminate\Contracts\Container\Container;
use Lathe\Assets\AssetManifest;
use Lathe\Auth\FortifyConfig;
use Lathe\Dashboards\DashboardRegistry;
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

    public function discoverResources(string $directory, ?string $namespace = null): void
    {
        $this->resources()->discover($directory, $namespace);
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
