<?php

namespace Lathe\Http;

use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Lathe\Http\Controllers\DashboardController;
use Lathe\Http\Middleware\EnsureJson;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Support\ConfigStore;

/**
 * Registers the panel's page and JSON routes under lathe.path, on lathe.domain when set.
 */
class PanelRoutes
{
    public function __construct(protected Router $router, protected ConfigStore $config)
    {
        //
    }

    public function register(): void
    {
        $this->group()->middleware('lathe.api')->group(function (Router $router) {
            $router->get('/', DashboardController::class)->name('lathe.dashboard');
        });

        $this->group()
            ->prefix(trim($this->config->string('path'), '/').'/api')
            ->middleware(['lathe.api', EnsureJson::class])
            ->withoutMiddleware(HandleInertiaRequests::class)
            ->name('lathe.api.')
            ->group(function (Router $router) {
                //
            });
    }

    protected function group(): RouteRegistrar
    {
        $registrar = $this->router->prefix(trim($this->config->string('path'), '/'));
        $domain = $this->config->nullableString('domain');

        return $domain !== null ? $registrar->domain($domain) : $registrar;
    }
}
