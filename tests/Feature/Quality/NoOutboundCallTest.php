<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route as Router;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/**
 * Stands in for the http, https and ftp stream wrappers, so file_get_contents() or fopen()
 * on a URL fails the test instead of reaching the network.
 */
class OutboundStreamGuard
{
    /** @var list<string> */
    public static array $attempts = [];

    public mixed $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool
    {
        static::$attempts[] = $path;

        return false;
    }
}

const OUTBOUND_SCHEMES = ['http', 'https', 'ftp', 'ftps'];

// A value for each route parameter, taken from the seeded workbench; a 404 or 422 is fine.
const OUTBOUND_ROUTE_VALUES = [
    'resource' => 'orders', 'resourceId' => '1', 'id' => '1', 'relatedId' => '1', 'dashboard' => 'main',
    'widget' => 'orders', 'lens' => 'pending-orders', 'action' => 'mark-shipped', 'field' => 'customer',
    'filter' => 'status', 'relationship' => 'items', 'export' => '1', 'import' => '1', 'upload' => '1',
    'plugin' => 'heartbeat', 'page' => 'heartbeat', 'path' => 'ping', 'component' => 'none', 'file' => 'none.js',
    'hash' => 'none', 'token' => 'none', 'type' => 'bulk', 'any' => 'none', 'savedTab' => '1', 'draftId' => 'none', 'index' => '0',
];

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    Http::preventStrayRequests();
    OutboundStreamGuard::$attempts = [];
    foreach (OUTBOUND_SCHEMES as $scheme) {
        stream_wrapper_unregister($scheme);
        stream_wrapper_register($scheme, OutboundStreamGuard::class);
    }
});

afterEach(function () {
    foreach (OUTBOUND_SCHEMES as $scheme) {
        stream_wrapper_restore($scheme);
    }
});

it('calls out from no panel page and no API endpoint', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $failures = [];
    $sent = 0;

    foreach (Router::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        if (! str_starts_with($route->uri(), 'lathe')) {
            continue;
        }

        $uri = preg_replace_callback('/\{(\w+)\??\}/', function (array $match) use ($route, &$failures) {
            return OUTBOUND_ROUTE_VALUES[$match[1]] ?? $failures[] = "no value for {{$match[1]}} in {$route->uri()}";
        }, $route->uri());

        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS', 'QUERY']) as $method) {
            // A logout or impersonation route earlier in the loop must not leave the rest unauthenticated.
            $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
            $response = $this->json($method, '/'.$uri);
            $sent++;

            if ($response->status() === 401) {
                $failures[] = "{$method} /{$uri} answered 401, so it was never exercised";
            }

            if ($response->status() >= 500) {
                $failures[] = "{$method} /{$uri} answered {$response->status()}: ".substr((string) $response->getContent(), 0, 200);
            }
        }
    }

    expect($sent)->toBeGreaterThan(100)
        ->and($failures)->toBe([])
        ->and(OutboundStreamGuard::$attempts)->toBe([]);
});

it('calls out from no command except lathe:preset', function () {
    // The generators, install, uninstall, stubs, assets and translate commands write into the app;
    // the source check below covers them.
    foreach ([
        'lathe:doctor' => [], 'lathe:plugins' => [], 'lathe:cache' => [], 'lathe:clear' => [],
        'lathe:prune' => [], 'lathe:prune-action-events' => [], 'lathe:api-check' => [],
        'lathe:user' => ['--name' => 'Outbound', '--email' => 'outbound@example.test', '--password' => 'password'],
    ] as $command => $arguments) {
        Artisan::call($command, $arguments);
    }

    expect(OutboundStreamGuard::$attempts)->toBe([]);
});

it('opens no socket and no HTTP client outside lathe:preset in the source', function () {
    $root = dirname(__DIR__, 3);
    $hits = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/src", FilesystemIterator::SKIP_DOTS)) as $file) {
        $code = (string) file_get_contents($file->getPathname());
        $relative = substr($file->getPathname(), strlen($root) + 1);

        if (preg_match('/\b(curl_init|curl_exec|fsockopen|pfsockopen|stream_socket_client|socket_create|socket_connect)\s*\(|new\s+\\\\?GuzzleHttp\\\\Client\b|Http::(get|post|put|patch|delete|send|withHeaders|acceptJson|asJson|baseUrl|timeout|retry)\b|https?:\/\/[^\s\'"]+[\'"]\s*\)/', $code) === 1
            && $relative !== 'src/Console/PresetCommand.php') {
            $hits[] = $relative;
        }
    }

    expect($hits)->toBe([]);
});
