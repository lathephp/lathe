<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Fortify;
use Lathe\Lathe;
use Lathe\LatheServiceProvider;
use Lathe\Tests\Fixtures\Plugins\Beacon;
use Lathe\Tests\Fixtures\Plugins\Broken;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Beacon::$calls = [];
});

function signInThroughThePanel(string $email): void
{
    test()->post('/lathe/login', ['email' => $email, 'password' => 'password'])->assertRedirect();
}

it('sends a signed-in user who opens the login page to the panel', function () {
    signInThroughThePanel('admin@lathephp.com');

    $this->get('/lathe/login')->assertRedirect('/lathe');
});

it('runs serving() after the session user is known, and only for users who may open the panel', function () {
    Lathe::registerPlugins(Beacon::make()->canSee(fn ($request) => $request->user() !== null));
    reloadLatheRoutes();

    $this->get('/lathe/plugins/beacon')->assertRedirect('/lathe/login');
    expect(Beacon::$calls)->not->toContain('serving');

    signInThroughThePanel('guest@lathephp.com');
    $this->get('/lathe/plugins/beacon')->assertForbidden();
    expect(Beacon::$calls)->not->toContain('serving');

    $this->post('/lathe/logout');
    signInThroughThePanel('admin@lathephp.com');
    $this->get('/lathe/plugins/beacon')->assertOk();
    expect(Beacon::$calls)->toContain('serving');
});

it('draws a CSRF failure before the panel props as a full-screen 419', function () {
    signInThroughThePanel('admin@lathephp.com');
    app('router')->prependMiddlewareToGroup('web', ThrowsTokenMismatch::class);

    $this->get('/lathe')->assertStatus(419)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Error', false)
        ->where('status', 419)
        ->where('shell', false));
});

it('shows a 500 full screen when the shell itself cannot be built', function () {
    config(['app.debug' => false]);
    Lathe::registerPlugins(Broken::make());
    signInThroughThePanel('admin@lathephp.com');

    $this->get('/lathe')->assertStatus(500)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Error', false)
        ->where('status', 500)
        ->where('shell', false));
});

it('leaves Fortify routes to a host that publishes config/fortify.php', function () {
    $config = sys_get_temp_dir().'/lathe-config-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($config);
    Fortify::$registersRoutes = true;

    app()->useConfigPath($config);
    (new LatheServiceProvider(app()))->register();
    expect(Fortify::$registersRoutes)->toBeFalse();

    Fortify::$registersRoutes = true;
    File::put("{$config}/fortify.php", '<?php return [];');
    (new LatheServiceProvider(app()))->register();
    expect(Fortify::$registersRoutes)->toBeTrue();

    File::deleteDirectory($config);
});

class ThrowsTokenMismatch
{
    public function handle(mixed $request, Closure $next): never
    {
        throw new TokenMismatchException('CSRF token mismatch.');
    }
}
