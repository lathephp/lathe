<?php

namespace Lathe\Http;

use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Lathe\Auth\FortifyConfig;
use Lathe\Http\Controllers\Assets\AssetController;
use Lathe\Http\Controllers\Auth\AuthenticatedSessionController;
use Lathe\Http\Controllers\Auth\LoginPageController;
use Lathe\Http\Controllers\DashboardController;
use Lathe\Http\Controllers\PageFallbackController;
use Lathe\Http\Controllers\Plugins\ModuleController;
use Lathe\Http\Controllers\Plugins\PluginApiController;
use Lathe\Http\Controllers\Plugins\PluginPageController;
use Lathe\Http\Controllers\Resources\CountController;
use Lathe\Http\Controllers\Resources\DeleteController;
use Lathe\Http\Controllers\Resources\FilterController;
use Lathe\Http\Controllers\Resources\IndexController;
use Lathe\Http\Controllers\Resources\IndexPageController;
use Lathe\Http\Controllers\Resources\SavedTabController;
use Lathe\Http\Middleware\Authenticate;
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
        protected FortifyConfig $fortify,
    ) {
        //
    }

    public function register(): void
    {
        // Prebuilt files load without a session, so they set no cookie.
        $this->group()->group(function (Router $router) {
            $router->get('assets/{hash}/{file}', AssetController::class)->name('lathe.assets');
            $router->get('modules/{file}', ModuleController::class)->name('lathe.modules');
            $router->get('styles/{file}', ModuleController::class)->defaults('type', 'styles')->name('lathe.styles');
        });

        $this->group()->middleware('lathe')->name('lathe.')->group(function (Router $router) {
            $guest = 'lathe.guest:'.$this->fortify->guard();

            $router->get('login', LoginPageController::class)->middleware($guest)->name('login');
            $router->post('login', [AuthenticatedSessionController::class, 'store'])->middleware($guest)->name('login.store');
            // POST only: a GET would let any image tag sign the user out.
            $router->post('logout', [AuthenticatedSessionController::class, 'destroy'])->middleware(Authenticate::class)->name('logout');
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
            $router->get('resources/{resource}/filters', [FilterController::class, 'index'])->name('resources.filters');
            $router->get('resources/{resource}/filters/{filter}/options', [FilterController::class, 'options'])->name('resources.filters.options');
            $router->get('resources/{resource}/saved-tabs', [SavedTabController::class, 'index'])->name('resources.savedTabs.index');
            $router->post('resources/{resource}/saved-tabs', [SavedTabController::class, 'store'])->name('resources.savedTabs.store');
            $router->put('resources/{resource}/saved-tabs/{savedTab}', [SavedTabController::class, 'update'])->name('resources.savedTabs.update');
            $router->delete('resources/{resource}/saved-tabs/{savedTab}', [SavedTabController::class, 'destroy'])->name('resources.savedTabs.destroy');
            $router->delete('resources/{resource}/{resourceId}', [DeleteController::class, 'delete'])->name('resources.delete');
            $router->delete('resources/{resource}/{resourceId}/force', [DeleteController::class, 'forceDelete'])->name('resources.forceDelete');
            $router->put('resources/{resource}/{resourceId}/restore', [DeleteController::class, 'restore'])->name('resources.restore');

            // Registered after every plugin's own routes, so those win.
            $router->any('plugins/{plugin}/{path?}', PluginApiController::class)
                ->where('path', '.*')
                ->name('plugins');

            // Matches last: an unknown API URL still runs Authenticate, so signed out it answers 401, not 404.
            $router->any('{any}', fn () => abort(404))->where('any', '.*')->fallback()->name('fallback');
        });

        // An unknown page answers the Error page: in the shell when signed in, full screen when not.
        $this->group()->middleware('lathe')->group(function (Router $router) {
            $router->any('{any}', PageFallbackController::class)->where('any', '.*')->fallback()->name('lathe.fallback');
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
