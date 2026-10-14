<?php

namespace Acme\Heartbeat;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Lathe\Enums\Icon;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Plugins\Plugin;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Heartbeat extends Plugin
{
    public function menu(Request $request): ?array
    {
        return [MenuSection::section('Heartbeat', [MenuItem::plugin(static::class)->icon(Icon::Activity)])];
    }

    public function assets(): array
    {
        return ['scripts' => ['heartbeat' => __DIR__.'/../dist/heartbeat.js']];
    }

    public function routes(Router $router): void
    {
        $router->get('ping', fn () => new JsonResponse(['pong' => true]));
    }

    public function handleRequest(Request $request, string $path): Response
    {
        if (! $request->isMethod('GET') || $path !== 'status') {
            return parent::handleRequest($request, $path);
        }

        return new JsonResponse([
            'app' => config('app.name'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'database' => $this->database(),
            'time' => now()->toISOString(),
        ]);
    }

    protected function database(): string
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            return 'down';
        }

        return 'connected';
    }
}
