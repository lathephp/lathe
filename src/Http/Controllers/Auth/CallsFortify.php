<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Lathe\Auth\FortifyConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs a Fortify controller action for the panel.
 *
 * Fortify's controllers, form requests, pipelines and responses read fortify.* when they are
 * built or rendered, so all of that happens inside FortifyConfig::scoped().
 */
trait CallsFortify
{
    /**
     * @param  class-string  $controller
     */
    protected function callFortify(Request $request, string $controller, string $method): Response
    {
        return Container::getInstance()->make(FortifyConfig::class)->scoped(function () use ($request, $controller, $method) {
            $container = Container::getInstance();
            $instance = $container->make($controller);

            /** @var callable $action */
            $action = [$instance, $method];

            return Router::toResponse($request, $container->call($action));
        });
    }
}
