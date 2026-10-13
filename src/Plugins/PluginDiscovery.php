<?php

namespace Lathe\Plugins;

/**
 * Registers the plugin each installed Composer package names under extra.lathe.plugin.
 */
class PluginDiscovery
{
    public function __construct(protected string $vendorPath)
    {
        //
    }

    public function register(PluginRegistry $registry): void
    {
        foreach ($this->classes() as $class) {
            $registry->register($class::make());
        }
    }

    /**
     * @return list<class-string<Plugin>>
     */
    public function classes(): array
    {
        $file = $this->vendorPath.'/composer/installed.json';

        if (! is_file($file)) {
            return [];
        }

        $installed = json_decode((string) file_get_contents($file), true);
        $packages = is_array($installed) ? ($installed['packages'] ?? $installed) : [];
        $classes = [];

        foreach (is_array($packages) ? $packages : [] as $package) {
            $class = is_array($package) ? data_get($package, 'extra.lathe.plugin') : null;

            if (is_string($class) && is_subclass_of($class, Plugin::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
