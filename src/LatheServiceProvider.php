<?php

namespace Lathe;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Lathe\Assets\AssetManifest;
use Lathe\Assets\AssetRegistry;
use Lathe\Assets\RootTags;
use Lathe\Auth\FortifyConfig;
use Lathe\Console\ApiCheckCommand;
use Lathe\Console\CacheCommand;
use Lathe\Console\ClearCommand;
use Lathe\Console\DoctorCommand;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Http\Middleware\ServeLathe;
use Lathe\Http\Middleware\ServePlugins;
use Lathe\Http\PanelRoutes;
use Lathe\Plugins\PluginDiscovery;
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
        $this->app->singleton(AssetRegistry::class);
        $this->app->singleton(FortifyConfig::class);

        // Lathe registers login and logout under its own path; Fortify's routes at the host root stay off.
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        $this->validateConfig();

        AboutCommand::add('Lathe', fn () => ['Version' => Lathe::version()]);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'lathe');
        Blade::directive('latheStyles', fn () => '<?php echo app('.RootTags::class.'::class)->styles(); ?>');
        Blade::directive('latheScripts', fn () => '<?php echo app('.RootTags::class.'::class)->scripts(); ?>');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerMiddleware();

        // After every provider has booted: hand-registered plugins first, then discovered
        // ones, then the routes, so package routes land before the plugin catch-all.
        $this->app->booted(function () {
            (new PluginDiscovery($this->app->make(PackageManifest::class)->vendorPath))
                ->register($this->app->make(PluginRegistry::class));

            if (! $this->app->routesAreCached()) {
                $this->app->make(PanelRoutes::class)->register();
                $this->app->make(Router::class)->getRoutes()->refreshNameLookups();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ApiCheckCommand::class, CacheCommand::class, ClearCommand::class, DoctorCommand::class]);
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
