<?php

namespace Lathe\Http;

use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Lathe\Http\Controllers\DashboardController;
use Lathe\Http\Controllers\Plugins\ModuleController;
use Lathe\Http\Controllers\Plugins\PluginApiController;
use Lathe\Http\Controllers\Plugins\PluginPageController;
use Lathe\Http\Controllers\Resources\CountController;
use Lathe\Http\Controllers\Resources\IndexController;
use Lathe\Http\Controllers\Resources\IndexPageController;
use Lathe\Http\Middleware\AuthorizePlugin;
use Lathe\Http\Middleware\EnsureJson;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Plugins\PluginRegistry;
use Lathe\Support\ConfigStore;

/**
 * Registers the panel's page and JSON routes under lathe.path, on lathe.domain when set.
 */
class PanelRoutes
{
    public function __construct(
        protected Router $router,
        protected ConfigStore $config,
        protected PluginRegistry $plugins,
    ) {
        //
    }

    public function register(): void
    {
        // Prebuilt files load without a session, so they set no cookie.
        $this->group()->group(function (Router $router) {
            $router->get('modules/{file}', ModuleController::class)->name('lathe.modules');
            $router->get('styles/{file}', ModuleController::class)->defaults('type', 'styles')->name('lathe.styles');
        });

        $this->group()->middleware('lathe.api')->group(function (Router $router) {
            $router->get('/', DashboardController::class)->name('lathe.dashboard');
            $router->get('resources/{resource}', IndexPageController::class)->name('lathe.resources.index');
            $router->get('plugins/{plugin}/{path?}', PluginPageController::class)
                ->where('path', '.*')
                ->name('lathe.plugins.show');
        });

        foreach ($this->plugins->all() as $plugin) {
            $this->api()
                ->prefix($this->apiPrefix().'/plugins/'.$plugin::uriKey())
                ->middleware(AuthorizePlugin::class.':'.$plugin::uriKey())
                ->group(fn (Router $router) => $plugin->routes($router));
        }

        $this->api()->prefix($this->apiPrefix())->name('lathe.api.')->group(function (Router $router) {
            $router->get('resources/{resource}', IndexController::class)->name('resources.index');
            $router->get('resources/{resource}/count', CountController::class)->name('resources.count');

            // Registered after every plugin's own routes, so those win.
            $router->any('plugins/{plugin}/{path?}', PluginApiController::class)
                ->where('path', '.*')
                ->name('plugins');
        });
    }

    protected function api(): RouteRegistrar
    {
        return $this->group()
            ->middleware(['lathe.api', EnsureJson::class])
            ->withoutMiddleware(HandleInertiaRequests::class);
    }

    protected function apiPrefix(): string
    {
        return trim($this->config->string('path'), '/').'/api';
    }

    protected function group(): RouteRegistrar
    {
        $registrar = $this->router->prefix(trim($this->config->string('path'), '/'));
        $domain = $this->config->nullableString('domain');

        return $domain !== null ? $registrar->domain($domain) : $registrar;
    }
}
