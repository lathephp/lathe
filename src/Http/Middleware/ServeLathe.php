<?php

namespace Lathe\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lathe\Assets\AssetRegistry;
use Lathe\Events\ServingLathe;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\LatheManager;
use Lathe\Support\TranslationManager;
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
            $this->addRemoteOrigins($response);
        } finally {
            $this->container->forgetInstance(LatheRequest::class);
            $this->container->forgetInstance(TranslationManager::REQUEST_BAG);
            $this->container->forgetInstance(AssetRegistry::REQUEST_BAG);
            $this->container->forgetInstance(LatheManager::PROVIDED_BAG);
        }

        $response->headers->set('X-Lathe-Version', Lathe::version());
        $response->headers->set('X-Lathe-Assets', Lathe::assets()->hash());

        return $response;
    }

    /**
     * Lets the origins of remote assets into the script-src and style-src the host's policy set on
     * this response. A response without a policy stays without one.
     */
    protected function addRemoteOrigins(Response $response): void
    {
        $policy = $response->headers->get('Content-Security-Policy');

        if ($policy !== null) {
            $response->headers->set('Content-Security-Policy', $this->container->make(AssetRegistry::class)->extendPolicy($policy));
        }
    }
}
