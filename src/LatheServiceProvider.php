<?php

namespace Lathe;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Lathe\Assets\AssetManifest;
use Lathe\Auth\FortifyConfig;
use Lathe\Console\CacheCommand;
use Lathe\Console\ClearCommand;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Http\Middleware\ServeLathe;
use Lathe\Http\Middleware\ServePlugins;
use Lathe\Http\PanelRoutes;
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

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'lathe');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerMiddleware();

        if (! $this->app->routesAreCached()) {
            $this->app->make(PanelRoutes::class)->register();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CacheCommand::class, ClearCommand::class]);
            $this->optimizes(optimize: 'lathe:cache', clear: 'lathe:clear', key: 'lathe');

            $this->publishes([
                __DIR__.'/../config/lathe.php' => config_path('lathe.php'),
            ], 'lathe-config');

            // Plain publishes keeps the fixed migration stamps; publishesMigrations would restamp them.
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'lathe-migrations');
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $config = $this->app->make(ConfigStore::class);

        /** @var list<string> $middleware */
        $middleware = $config->get('middleware', []);
        /** @var list<string> $apiMiddleware */
        $apiMiddleware = $config->get('api_middleware', []);

        $router->middlewareGroup('lathe', [ServePlugins::class, ...$middleware]);
        $router->middlewareGroup('lathe.api', $apiMiddleware);
        $router->aliasMiddleware('lathe.guest', RedirectIfAuthenticated::class);

        $this->app->make(Kernel::class)->pushMiddleware(ServeLathe::class);
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
