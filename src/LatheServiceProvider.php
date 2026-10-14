<?php

namespace Lathe;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Lathe\ActionEvents\ActionEventListener;
use Lathe\ActionEvents\ActionEventPolicy;
use Lathe\ActionEvents\ActionEventRecorder;
use Lathe\ActionEvents\ActionEventResource;
use Lathe\Assets\AssetManifest;
use Lathe\Assets\AssetRegistry;
use Lathe\Assets\RootTags;
use Lathe\Auth\FortifyConfig;
use Lathe\Console\ApiCheckCommand;
use Lathe\Console\CacheCommand;
use Lathe\Console\ClearCommand;
use Lathe\Console\DoctorCommand;
use Lathe\Console\InstallCommand;
use Lathe\Console\MakeActionCommand;
use Lathe\Console\MakeDashboardCommand;
use Lathe\Console\MakeFilterCommand;
use Lathe\Console\MakeLensCommand;
use Lathe\Console\MakeResourceCommand;
use Lathe\Console\PruneActionEventsCommand;
use Lathe\Console\TranslateCommand;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Http\ErrorPages;
use Lathe\Http\Middleware\RedirectIfAuthenticated;
use Lathe\Http\Middleware\ServeLathe;
use Lathe\Http\Middleware\ServePlugins;
use Lathe\Http\PanelRoutes;
use Lathe\Menu\MenuRegistry;
use Lathe\Notifications\NotificationChannelRegistry;
use Lathe\Plugins\PluginDiscovery;
use Lathe\Plugins\PluginRegistry;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\ConfigStore;
use Lathe\Support\ConfigValidator;
use Lathe\Support\TranslationManager;
use Throwable;

class LatheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lathe.php', 'lathe');

        $this->app->singleton(LatheManager::class);
        $this->app->singleton(ConfigStore::class);
        $this->app->singleton(TranslationManager::class, fn () => new TranslationManager(__DIR__.'/../lang'));
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(PluginRegistry::class);
        $this->app->singleton(MenuRegistry::class);
        $this->app->singleton(DashboardRegistry::class);
        $this->app->singleton(AssetManifest::class);
        $this->app->singleton(AssetRegistry::class);
        $this->app->singleton(FortifyConfig::class);
        $this->app->singleton(NotificationChannelRegistry::class, fn () => new NotificationChannelRegistry(fn () => $this->app->isBooted()));
        $this->app->scoped(ActionEventRecorder::class);

        // Lathe registers login and logout under its own path. Fortify's routes at the host root
        // stay off, unless the host published config/fortify.php and so uses Fortify itself.
        if (! file_exists($this->app->configPath('fortify.php'))) {
            Fortify::ignoreRoutes();
        }
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
        $this->app->make(Dispatcher::class)->subscribe(ActionEventListener::class);
        $this->app->make(ResourceRegistry::class)->register(ActionEventResource::class);

        // The sidebar writes this cookie in the browser, so it is read unencrypted.
        EncryptCookies::except(['sidebar_state']);

        $this->app->make(ExceptionHandler::class)->renderable(
            fn (Throwable $e, Request $request) => $this->app->make(ErrorPages::class)->render($e, $request),
        );

        // After every provider has booted: hand-registered plugins first, then discovered
        // ones, then the routes, so package routes land before the plugin catch-all.
        $this->app->booted(function () {
            // The log denies everyone until the host registers a policy of its own.
            if (Gate::getPolicyFor(Lathe::actionEventModel()) === null) {
                Gate::policy(Lathe::actionEventModel(), ActionEventPolicy::class);
            }

            (new PluginDiscovery(PluginDiscovery::vendorPath($this->app->make(PackageManifest::class)->vendorPath)))
                ->register($this->app->make(PluginRegistry::class));

            if (! $this->app->routesAreCached()) {
                $this->app->make(PanelRoutes::class)->register();
                $this->app->make(Router::class)->getRoutes()->refreshNameLookups();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ApiCheckCommand::class, CacheCommand::class, ClearCommand::class, DoctorCommand::class, InstallCommand::class, MakeActionCommand::class, MakeDashboardCommand::class, MakeFilterCommand::class, MakeLensCommand::class, MakeResourceCommand::class, PruneActionEventsCommand::class, TranslateCommand::class]);
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

        // ServePlugins runs last, once the session has resolved the user.
        $router->middlewareGroup('lathe', [...$middleware, ServePlugins::class]);
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
