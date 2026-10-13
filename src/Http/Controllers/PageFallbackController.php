<?php

namespace Lathe\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The last panel page route: 405 when the URL exists under another verb, else 404.
 */
class PageFallbackController
{
    public function __invoke(Request $request, Router $router): never
    {
        $allowed = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! $route->isFallback && ! in_array($request->getMethod(), $route->methods(), true) && $route->matches($request, false)) {
                $allowed = [...$allowed, ...$route->methods()];
            }
        }

        if ($allowed !== []) {
            throw new MethodNotAllowedHttpException(array_values(array_unique($allowed)));
        }

        throw new NotFoundHttpException;
    }
}
