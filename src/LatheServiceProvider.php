<?php

namespace Lathe;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Events\VendorTagPublished;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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
use Lathe\Auth\Contracts\ImpersonatesUsers;
use Lathe\Auth\FortifyBootstrap;
use Lathe\Auth\FortifyConfig;
use Lathe\Auth\Impersonation;
use Lathe\Console\ApiCheckCommand;
use Lathe\Console\AssetsCommand;
use Lathe\Console\CacheCommand;
use Lathe\Console\ClearCommand;
use Lathe\Console\DoctorCommand;
use Lathe\Console\InstallCommand;
use Lathe\Console\MakeActionCommand;
use Lathe\Console\MakeDashboardCommand;
use Lathe\Console\MakeFieldCommand;
use Lathe\Console\MakeFilterCommand;
use Lathe\Console\MakeLensCommand;
use Lathe\Console\MakePageCommand;
use Lathe\Console\MakePluginCommand;
use Lathe\Console\MakeResourceCommand;
use Lathe\Console\MakeResourcePluginCommand;
use Lathe\Console\MakeWidgetCommand;
use Lathe\Console\PluginsCommand;
use Lathe\Console\PresetCommand;
use Lathe\Console\PruneActionEventsCommand;
use Lathe\Console\PruneCommand;
use Lathe\Console\StubsCommand;
use Lathe\Console\TranslateCommand;
use Lathe\Console\UninstallCommand;
use Lathe\Console\UserCommand;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Http\ErrorPages;
use Lathe\Http\Middleware\RedirectIfAuthenticated;
use Lathe\Http\Middleware\ServeLathe;
use Lathe\Http\Middleware\ServePlugins;
use Lathe\Http\PanelRoutes;
use Lathe\Menu\MenuRegistry;
use Lathe\Notifications\NotificationChannelRegistry;
use Lathe\Pages\PageRegistry;
use Lathe\Plugins\PluginDiscovery;
use Lathe\Plugins\PluginRegistry;
use Lathe\Plugins\PluginSettings;
use Lathe\Plugins\ResourcePluginRegistry;
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
        $this->app->singleton(ResourcePluginRegistry::class);
        $this->app->singleton(MenuRegistry::class);
        $this->app->singleton(DashboardRegistry::class);
        $this->app->singleton(PageRegistry::class);
        $this->app->singleton(AssetManifest::class);
        $this->app->singleton(AssetRegistry::class);
        $this->app->singleton(FortifyBootstrap::class);
        $this->app->alias(FortifyBootstrap::class, FortifyConfig::class);
        $this->app->bind(ImpersonatesUsers::class, Impersonation::class);
        $this->app->singleton(NotificationChannelRegistry::class, fn () => new NotificationChannelRegistry(fn () => $this->app->isBooted()));
        $this->app->scoped(ActionEventRecorder::class);
        $this->app->scoped(PluginSettings::class);

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
        Blade::directive('latheFontLink', fn () => '<?php echo app('.RootTags::class.'::class)->fontLink(); ?>');
        Blade::directive('latheScripts', fn () => '<?php echo app('.RootTags::class.'::class)->scripts(); ?>');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerMiddleware();
        $this->app->make(Dispatcher::class)->subscribe(ActionEventListener::class);
        $this->app->make(ResourceRegistry::class)->register(ActionEventResource::class);

        // The sidebar writes this cookie in the browser, so it is read unencrypted.
        EncryptCookies::except(['sidebar_state']);

        // The maintenance page is a panel page, so it needs the built files while the app is down.
        // Laravel's prerendered maintenance check matches paths only, so the domain plays no part.
        $path = trim($this->app->make(ConfigStore::class)->string('path'), '/');
        PreventRequestsDuringMaintenance::except(["{$path}/assets/*", "{$path}/modules/*", "{$path}/styles/*"]);

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

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('lathe:prune')->daily();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ApiCheckCommand::class, AssetsCommand::class, CacheCommand::class, ClearCommand::class, DoctorCommand::class, InstallCommand::class, MakeActionCommand::class, MakeDashboardCommand::class, MakeFieldCommand::class, MakeFilterCommand::class, MakeLensCommand::class, MakePageCommand::class, MakePluginCommand::class, MakeResourceCommand::class, MakeResourcePluginCommand::class, MakeWidgetCommand::class, PluginsCommand::class, PresetCommand::class, PruneActionEventsCommand::class, PruneCommand::class, StubsCommand::class, TranslateCommand::class, UninstallCommand::class, UserCommand::class]);
            $this->optimizes(optimize: 'lathe:cache', clear: 'lathe:clear', key: 'lathe');

            $this->publishes([
                __DIR__.'/../config/lathe.php' => config_path('lathe.php'),
            ], 'lathe-config');

            // Plain publishes keeps the fixed migration stamps; publishesMigrations would restamp them.
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'lathe-migrations');

            $this->publishes([
                __DIR__.'/../lang/en.json' => lang_path('vendor/lathe/en.json'),
            ], 'lathe-translations');

            $this->publishes([
                __DIR__.'/../stubs/user-resource.stub' => app_path('Lathe/Resources/User.php'),
            ], 'lathe-resource');

            // A publish copies the stub as is; fill its placeholders the way lathe:install does.
            Event::listen(VendorTagPublished::class, function (VendorTagPublished $event): void {
                if ($event->tag === 'lathe-resource') {
                    array_map($this->fillUserResource(...), array_filter(array_values($event->paths), 'is_string'));
                }
            });
        }
    }

    /**
     * Fill the placeholders of a published User Resource with Laravel's default App\ namespace
     * (asking the app for its namespace here would cache it) and the auth model.
     *
     * @internal
     */
    protected function fillUserResource(string $path): void
    {
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        if (! str_contains($contents, '{{ namespace }}')) {
            return;
        }

        $model = config('auth.providers.users.model');

        file_put_contents($path, str_replace(['{{ namespace }}', '{{ model }}'], ['App\\', is_string($model) ? $model : 'App\Models\User'], $contents));
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
