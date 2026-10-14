<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Lathe;
use Lathe\LatheManager;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);

    User::factory()->create(['email' => 'admin@lathephp.com']);
});

it('keeps reset routes off and authentication routes on by default', function () {
    // The workbench turns reset routes on, so the defaults are read from a fresh manager.
    $manager = new LatheManager(app());

    expect($manager->hasPasswordResetRoutes())->toBeFalse()
        ->and($manager->hasAuthenticationRoutes())->toBeTrue()
        ->and($manager->customLoginPath())->toBeNull()
        ->and($manager->customForgotPasswordPath())->toBeNull();
});

it('turns the reset routes on and off, with custom paths for the client', function () {
    Lathe::withPasswordResetRoutes();
    expect(Lathe::hasPasswordResetRoutes())->toBeTrue();

    Lathe::withoutPasswordResetRoutes('/forgot', '/reset');

    expect(Lathe::hasPasswordResetRoutes())->toBeFalse()
        ->and(Lathe::customForgotPasswordPath())->toBe('/forgot')
        ->and(Lathe::customResetPasswordPath())->toBe('/reset');

    $this->get('/lathe/login')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('authPaths.forgotPassword', '/forgot')
        ->where('authPaths.resetPassword', '/reset'));
});

it('shares the panel auth paths, and no forgot link while reset routes are off', function () {
    Lathe::withoutPasswordResetRoutes();

    $this->get('/lathe/login')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('authPaths', [
            'login' => '/lathe/login',
            'logout' => '/lathe/logout',
            'forgotPassword' => null,
            'resetPassword' => null,
        ]));

    Lathe::withPasswordResetRoutes();

    $this->get('/lathe/login')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('authPaths.forgotPassword', '/lathe/forgot-password'));
});

it('switches the authentication routes off for the host paths, and back on', function () {
    Lathe::withoutAuthenticationRoutes('/sign-in', '/sign-out');

    expect(Lathe::hasAuthenticationRoutes())->toBeFalse()
        ->and(Lathe::customLoginPath())->toBe('/sign-in')
        ->and(Lathe::customLogoutPath())->toBe('/sign-out');

    reloadLatheRoutes();

    $this->get('/lathe/login')->assertNotFound();
    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])->assertNotFound();
    $this->get('/lathe')->assertRedirect('/sign-in');

    Lathe::withAuthenticationRoutes();
    reloadLatheRoutes();

    expect(Lathe::hasAuthenticationRoutes())->toBeTrue()
        ->and(Lathe::customLoginPath())->toBeNull();
    $this->get('/lathe/login')->assertOk();
});

it('lands a sign-in with no intended URL on the initial path', function () {
    Lathe::initialPath('/lathe/resources/orders');

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe/resources/orders');
});

it('hands the request to an initial path Closure', function () {
    Lathe::initialPath(fn (Request $request) => '/lathe/dashboards/'.$request->input('email'));

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe/dashboards/admin@lathephp.com');
});

it('lets the intended URL win over the initial path', function () {
    Lathe::initialPath('/lathe/resources/orders');
    Route::middleware('lathe.api')->get('lathe/reports', fn () => 'reports');

    $this->get('/lathe/reports')->assertRedirect('/lathe/login');

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe/reports');
});

it('resolves the panel root when no initial path is set', function () {
    $request = Request::create('/lathe/login');

    expect(Lathe::resolveInitialPath($request))->toBe('/lathe')
        ->and(Lathe::initialPathUrl($request))->toBe('http://localhost/lathe');

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe');
});
