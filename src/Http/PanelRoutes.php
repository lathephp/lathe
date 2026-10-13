<?php

namespace Lathe\Http;

use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Lathe\Auth\FortifyConfig;
use Lathe\Http\Controllers\Assets\AssetController;
use Lathe\Http\Controllers\Auth\AuthenticatedSessionController;
use Lathe\Http\Controllers\Auth\LoginPageController;
use Lathe\Http\Controllers\DashboardController;
use Lathe\Http\Controllers\GlobalSearchController;
use Lathe\Http\Controllers\PageFallbackController;
use Lathe\Http\Controllers\Plugins\ModuleController;
use Lathe\Http\Controllers\Plugins\PluginApiController;
use Lathe\Http\Controllers\Plugins\PluginPageController;
use Lathe\Http\Controllers\Resources\CountController;
use Lathe\Http\Controllers\Resources\DeleteController;
use Lathe\Http\Controllers\Resources\DetailPageController;
use Lathe\Http\Controllers\Resources\FilterController;
use Lathe\Http\Controllers\Resources\FormFieldsController;
use Lathe\Http\Controllers\Resources\FormPageController;
use Lathe\Http\Controllers\Resources\IndexController;
use Lathe\Http\Controllers\Resources\IndexPageController;
use Lathe\Http\Controllers\Resources\LensController;
use Lathe\Http\Controllers\Resources\RecordController;
use Lathe\Http\Controllers\Resources\SavedTabController;
use Lathe\Http\Controllers\Uploads\StartUploadController;
use Lathe\Http\Controllers\Uploads\UploadController;
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
            $router->get('resources/{resource}/lens/{lens}', [LensController::class, 'page'])->name('lathe.resources.lens');
            // Before {resourceId}, so "new" never reads as a record id.
            $router->get('resources/{resource}/new', [FormPageController::class, 'create'])->name('lathe.resources.create');
            $router->get('resources/{resource}/{resourceId}', DetailPageController::class)->name('lathe.resources.show');
            $router->get('resources/{resource}/{resourceId}/edit', [FormPageController::class, 'edit'])->name('lathe.resources.edit');
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
            $router->put('resources/{resource}/saved-tabs/reorder', [SavedTabController::class, 'reorder'])->name('resources.savedTabs.reorder');
            $router->put('resources/{resource}/saved-tabs/{savedTab}', [SavedTabController::class, 'update'])->name('resources.savedTabs.update');
            $router->delete('resources/{resource}/saved-tabs/{savedTab}', [SavedTabController::class, 'destroy'])->name('resources.savedTabs.destroy');
            // Before the {resourceId} routes, so a lens path never reads as a record id.
            $router->get('resources/{resource}/lenses/{lens}', [LensController::class, 'index'])->name('resources.lenses.index');
            $router->get('resources/{resource}/lenses/{lens}/count', [LensController::class, 'count'])->name('resources.lenses.count');
            $router->get('resources/{resource}/lenses/{lens}/filters', [LensController::class, 'filters'])->name('resources.lenses.filters');
            $router->get('resources/{resource}/creation-fields', [FormFieldsController::class, 'creation'])->name('resources.creationFields');
            $router->post('resources/{resource}', [RecordController::class, 'store'])->name('resources.store');
            $router->get('resources/{resource}/{resourceId}/update-fields', [FormFieldsController::class, 'update'])->name('resources.updateFields');
            $router->put('resources/{resource}/{resourceId}', [RecordController::class, 'update'])->name('resources.update');
            $router->delete('resources/{resource}/{resourceId}', [DeleteController::class, 'delete'])->name('resources.delete');
            $router->delete('resources/{resource}/{resourceId}/force', [DeleteController::class, 'forceDelete'])->name('resources.forceDelete');
            $router->put('resources/{resource}/{resourceId}/restore', [DeleteController::class, 'restore'])->name('resources.restore');
            $router->post('resources/{resource}/uploads/{field}', [StartUploadController::class, 'field'])->name('resources.uploads.start');
            $router->post('uploads', [StartUploadController::class, 'custom'])->name('uploads.start');
            $router->get('uploads/{upload}', [UploadController::class, 'show'])->name('uploads.show');
            $router->put('uploads/{upload}/chunks/{index}', [UploadController::class, 'chunk'])->where('index', '[0-9]+')->name('uploads.chunk');
            $router->post('uploads/{upload}/complete', [UploadController::class, 'complete'])->name('uploads.complete');
            $router->delete('uploads/{upload}', [UploadController::class, 'destroy'])->name('uploads.destroy');
            $router->get('search', GlobalSearchController::class)->name('search');

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
