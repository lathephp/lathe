<?php

namespace Lathe\Tests\Fixtures\Plugins;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Lathe\Plugins\Plugin;
use Symfony\Component\HttpFoundation\Response;

class Beacon extends Plugin
{
    /** @var list<string> */
    public static array $calls = [];

    public function register(): void
    {
        static::$calls[] = 'register';
    }

    public function boot(): void
    {
        static::$calls[] = 'boot';
    }

    public function serving(Request $request): void
    {
        static::$calls[] = 'serving';
    }

    public function assets(): array
    {
        return ['scripts' => ['beacon' => dirname(__DIR__).'/assets/beacon.js']];
    }

    public function routes(Router $router): void
    {
        $router->get('ping', fn () => new JsonResponse(['pong' => true]));
    }

    public function handleRequest(Request $request, string $path): Response
    {
        if ($request->isMethod('GET') && $path === 'status') {
            return new JsonResponse(['status' => 'ok']);
        }

        return parent::handleRequest($request, $path);
    }
}
