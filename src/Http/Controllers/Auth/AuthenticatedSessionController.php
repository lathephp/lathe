<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifySessionController;
use Lathe\Auth\FortifyConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs in and out through Fortify on the panel guard.
 *
 * Fortify's controller, its LoginRequest, its pipeline and its responses read fortify.* when
 * they are built or rendered, so all of that happens inside FortifyConfig::scoped().
 */
class AuthenticatedSessionController
{
    public function __construct(protected Container $container, protected FortifyConfig $fortify)
    {
        //
    }

    public function store(Request $request): Response
    {
        return $this->callFortify($request, 'store');
    }

    public function destroy(Request $request): Response
    {
        return $this->callFortify($request, 'destroy');
    }

    protected function callFortify(Request $request, string $method): Response
    {
        return $this->fortify->scoped(function () use ($request, $method) {
            $controller = $this->container->make(FortifySessionController::class);

            return Router::toResponse($request, $this->container->call($controller->{$method}(...)));
        });
    }
}
