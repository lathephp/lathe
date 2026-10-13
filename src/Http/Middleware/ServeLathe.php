<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lathe\Events\ServingLathe;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Support\Util;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: marks panel requests, binds their LatheRequest and stamps the response.
 */
class ServeLathe
{
    public function __construct(protected Container $container)
    {
        //
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Util::isLatheRequest($request)) {
            return $next($request);
        }

        $latheRequest = LatheRequest::createFrom($request);
        // Routing happens after this middleware, so read the route from the live request.
        $latheRequest->setRouteResolver(fn () => $request->route());
        $latheRequest->setUserResolver(fn (?string $guard = null) => $request->user($guard));
        $this->container->instance(LatheRequest::class, $latheRequest);

        try {
            event(new ServingLathe($request));

            $response = $next($request);
        } finally {
            $this->container->forgetInstance(LatheRequest::class);
        }

        $response->headers->set('X-Lathe-Version', Lathe::version());
        $response->headers->set('X-Lathe-Assets', Lathe::assets()->hash());

        return $response;
    }
}
