<?php

namespace Lathe;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Lathe\Assets\AssetManifest;
use Lathe\Auth\FortifyConfig;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Plugins\PluginRegistry;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\ConfigStore;
use Lathe\Support\ConfigValidator;

class LatheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lathe.php', 'lathe');

        $this->app->singleton(LatheManager::class);
        $this->app->singleton(ConfigStore::class);
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(PluginRegistry::class);
        $this->app->singleton(DashboardRegistry::class);
        $this->app->singleton(AssetManifest::class);
        $this->app->singleton(FortifyConfig::class);
    }

    public function boot(): void
    {
        $this->validateConfig();

        AboutCommand::add('Lathe', fn () => ['Version' => Lathe::version()]);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/lathe.php' => config_path('lathe.php'),
            ], 'lathe-config');
        }
    }

    protected function validateConfig(): void
    {
        $errors = $this->app->make(ConfigValidator::class)->errors(
            $this->app->make(ConfigStore::class)->all(),
        );

        if ($errors !== []) {
            throw new InvalidConfigurationException($errors[0]);
        }
    }
}
