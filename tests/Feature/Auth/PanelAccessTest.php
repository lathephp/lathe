<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Lathe;
use Lathe\LatheApplicationServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    // Testbench skips package discovery under PHPUnit, so the providers a host gets from it are registered here.
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    // A host runs Lathe's own migrations at install; the dashboard reads the preferences table.
    $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');

    $this->admin = User::factory()->create(['email' => 'admin@lathephp.com']);
    $this->editor = User::factory()->create(['email' => 'editor@lathephp.com']);
    $this->guest = User::factory()->create(['email' => 'guest@lathephp.com']);
});

it('denies every signed-in user while the viewLathe gate is undefined', function () {
    forgetGate('viewLathe');
    expect(Gate::has('viewLathe'))->toBeFalse();

    $this->actingAs($this->admin)->get('/lathe')->assertForbidden();
});

it('admits the allowlisted users of the workbench gate', function () {
    $this->app->register(WorkbenchLatheServiceProvider::class);

    $this->actingAs($this->admin)->get('/lathe')->assertOk();
    $this->actingAs($this->editor)->get('/lathe')->assertOk();
});

it('answers 403 to a signed-in user who is not allowlisted', function () {
    $this->app->register(WorkbenchLatheServiceProvider::class);

    $this->actingAs($this->guest)->get('/lathe')->assertForbidden();
    $this->actingAs($this->guest)->getJson('/lathe/api/resources/orders')
        ->assertForbidden()
        ->assertExactJson(['message' => 'This action is unauthorized.']);
});

it('closes the panel to the admin once the allowlist drops them and opens it again when added back', function () {
    $this->app->register(WorkbenchLatheServiceProvider::class);
    $this->actingAs($this->admin)->get('/lathe')->assertOk();

    $this->app->register(new class($this->app) extends WorkbenchLatheServiceProvider
    {
        protected function gate(): void
        {
            Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['editor@lathephp.com'], true));
        }
    }, force: true);
    $this->actingAs($this->admin)->get('/lathe')->assertForbidden();

    $this->app->register(WorkbenchLatheServiceProvider::class, force: true);
    $this->actingAs($this->admin)->get('/lathe')->assertOk();
});

it('keeps an unlisted user out with APP_ENV=local', function () {
    $this->app->detectEnvironment(fn () => 'local');
    expect($this->app->environment('local'))->toBeTrue();

    $this->actingAs($this->guest)->get('/lathe')->assertForbidden();

    $this->app->register(WorkbenchLatheServiceProvider::class);
    $this->actingAs($this->guest)->get('/lathe')->assertForbidden();
});

it('admits nobody through the default gate of a fresh install', function () {
    $this->app->register(new class($this->app) extends LatheApplicationServiceProvider {});

    expect(Gate::has('viewLathe'))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('viewLathe'))->toBeFalse();
    $this->actingAs($this->admin)->get('/lathe')->assertForbidden();
});

it('lets Lathe::auth() replace the gate check', function () {
    $this->app->register(WorkbenchLatheServiceProvider::class);

    Lathe::auth(fn () => true);
    $this->actingAs($this->guest)->get('/lathe')->assertOk();

    Lathe::auth(fn () => false);
    $this->actingAs($this->admin)->get('/lathe')->assertForbidden();
});

it('treats a user signed in only on another guard as signed out', function () {
    config(['auth.guards.panel' => ['driver' => 'session', 'provider' => 'users'], 'lathe.guard' => 'panel']);
    Lathe::auth(fn () => true);

    $this->actingAs($this->admin, 'web');

    $this->get('/lathe')->assertRedirect('/lathe/login');
    $this->get('/lathe/api/resources/orders')->assertStatus(401);
});

it('answers an API request without a session with 401 JSON, no redirect and no query', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get('/lathe/api/resources/orders')
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Unauthenticated.'])
        ->assertHeader('X-Lathe-Version', Lathe::version())
        ->assertHeader('X-Lathe-Assets', Lathe::assets()->hash())
        ->assertHeaderMissing('Location');

    expect($queries)->toBe(0);
});

it('answers 401 JSON on any API URL, page or not', function () {
    Route::prefix('lathe/api')->middleware('lathe.api')->get('metrics', fn () => ['ok' => true]);

    $this->get('/lathe/api/metrics')->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
    $this->get('/lathe/api/anything/else')->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
});

it('answers an unknown API URL with 404 JSON once signed in', function () {
    $this->app->register(WorkbenchLatheServiceProvider::class);

    $this->actingAs($this->admin)->get('/lathe/api/nothing/at/all')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
