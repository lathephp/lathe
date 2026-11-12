<?php

namespace Lathe\Http\Controllers\Auth;

use Illuminate\Container\Container;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Lathe\Auth\FortifyConfig;
use Lathe\Auth\Impersonation;
use Lathe\Lathe;
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

    /**
     * callFortify() for a request that checks the signed-in user's password: 5 tries a minute per
     * user, as the two-factor challenge takes, and the 6th answers 429. An impersonating admin's
     * tries count apart, so they never lock the target out.
     *
     * @internal
     *
     * @param  class-string  $controller
     */
    protected function callFortifyThrottled(Request $request, string $name, string $controller, string $method): Response
    {
        $user = $request->user(Lathe::fortify()->guard());
        $id = $user?->getAuthIdentifier();
        $by = $request->hasSession() ? $request->session()->get(Impersonation::IMPERSONATED_BY) : null;
        $key = "lathe.{$name}|".(is_scalar($id) ? $id : $request->ip()).(is_scalar($by) ? "|by:{$by}" : '');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new ThrottleRequestsException(headers: ['Retry-After' => RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key, 60);

        return $this->callFortify($request, $controller, $method);
    }
}
