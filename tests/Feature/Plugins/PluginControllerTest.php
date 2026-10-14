<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Lathe;
use Lathe\Plugins\Plugin;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Symfony\Component\HttpFoundation\Response;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
});

/**
 * A plugin whose handleRequest() echoes the verb and path, with one settings field.
 */
function registerEcho(): void
{
    $plugin = new class extends Plugin
    {
        public static function uriKey(): string
        {
            return 'echo';
        }

        public function settings(): array
        {
            return [Field::text('Motto')];
        }

        public function handleRequest(Request $request, string $path): Response
        {
            return new JsonResponse(['method' => $request->method(), 'path' => $path]);
        }
    };

    Lathe::registerPlugins($plugin);
    reloadLatheRoutes();
}

function controllerUser(): User
{
    return (new User)->forceFill(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test']);
}

it('sends the settings path to the settings handler even when handleRequest() answers everything', function () {
    registerEcho();
    $this->actingAs(controllerUser());

    $this->getJson('/lathe/api/plugins/echo/settings')
        ->assertOk()
        ->assertJsonPath('fields.0.attribute', 'motto');
    $this->putJson('/lathe/api/plugins/echo/settings', ['motto' => 'Onward'])->assertOk()->assertExactJson(['saved' => true]);
    $this->postJson('/lathe/api/plugins/echo/settings')->assertStatus(405);
});

it('pins every verb on any other path reaching handleRequest()', function () {
    registerEcho();
    $this->actingAs(controllerUser());

    $this->getJson('/lathe/api/plugins/echo/reports/7')->assertExactJson(['method' => 'GET', 'path' => 'reports/7']);
    $this->postJson('/lathe/api/plugins/echo/reports')->assertExactJson(['method' => 'POST', 'path' => 'reports']);
    $this->patchJson('/lathe/api/plugins/echo/reports/7')->assertExactJson(['method' => 'PATCH', 'path' => 'reports/7']);
    $this->deleteJson('/lathe/api/plugins/echo/reports/7')->assertExactJson(['method' => 'DELETE', 'path' => 'reports/7']);
});

it('pins the default handleRequest() as 404 and a refused authorize as 403', function () {
    Lathe::registerPlugins(Beacon::make());
    reloadLatheRoutes();
    $this->actingAs(controllerUser());

    $this->deleteJson('/lathe/api/plugins/beacon/anything')->assertNotFound();

    Lathe::plugins()->find('beacon')?->canSee(fn () => false);

    $this->getJson('/lathe/api/plugins/beacon/settings')->assertForbidden();
    $this->getJson('/lathe/api/plugins/beacon/anything')->assertForbidden();
});

it('runs a package route inside the panel API middleware: 401 signed out, 403 when refused, before the catch-all', function () {
    $this->getJson('/lathe/api/plugins/heartbeat/ping')->assertUnauthorized()->assertJsonStructure(['message']);

    $this->actingAs(controllerUser());
    $this->getJson('/lathe/api/plugins/heartbeat/ping')->assertOk()->assertExactJson(['pong' => true]);
    $this->getJson('/lathe/api/plugins/heartbeat/unknown')->assertNotFound();

    Lathe::plugins()->find('heartbeat')?->canSee(fn () => false);

    $this->getJson('/lathe/api/plugins/heartbeat/ping')->assertForbidden();
});
