<?php

use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Lathe;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);

    $this->admin = User::factory()->create(['email' => 'admin@lathephp.com']);

    // A request that needs a recent password confirmation, as the two-factor routes will.
    Route::middleware(['lathe.api', RequirePassword::using('lathe.password.confirm')])
        ->post('lathe/api/test-confirmed', fn () => response()->json(['ok' => true]));
    Route::middleware(['lathe', RequirePassword::using('lathe.password.confirm')])
        ->get('lathe/test-confirmed-page', fn () => 'confirmed page');
    Route::getRoutes()->refreshNameLookups();
});

afterEach(function () {
    Fortify::$confirmPasswordsUsingCallback = null;
});

it('answers 423 until the password is confirmed, then lets the request through', function () {
    $this->actingAs($this->admin);

    $this->postJson('/lathe/api/test-confirmed')
        ->assertStatus(423)
        ->assertExactJson(['message' => 'Password confirmation required.']);

    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();

    $this->postJson('/lathe/api/test-confirmed')->assertOk()->assertExactJson(['ok' => true]);
});

it('refuses a wrong password with 422 under password, and keeps answering 423', function () {
    $this->actingAs($this->admin);

    $this->postJson('/lathe/confirm-password', ['password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'The provided password was incorrect.']);

    $this->postJson('/lathe/api/test-confirmed')->assertStatus(423);
});

it('reports the confirmation as valid for the password timeout only', function () {
    $this->actingAs($this->admin);

    $this->getJson('/lathe/confirmed-password-status')->assertOk()->assertJson(['confirmed' => false]);

    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();

    $this->getJson('/lathe/confirmed-password-status')->assertOk()->assertJson(['confirmed' => true]);

    $this->travel(10_801)->seconds();

    $this->getJson('/lathe/confirmed-password-status')->assertOk()->assertJson(['confirmed' => false]);
    $this->postJson('/lathe/api/test-confirmed')->assertStatus(423);
});

it('confirms through the panel confirmPasswordsUsing hook', function () {
    Lathe::fortify()->confirmPasswordsUsing(fn ($user, ?string $password) => $password === 'magic');
    $this->actingAs($this->admin);

    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertUnprocessable();
    $this->postJson('/lathe/api/test-confirmed')->assertStatus(423);

    $this->postJson('/lathe/confirm-password', ['password' => 'magic'])->assertCreated();
    $this->postJson('/lathe/api/test-confirmed')->assertOk();
});

it('renders the confirm password page', function () {
    $this->actingAs($this->admin)
        ->get('/lathe/confirm-password')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ConfirmPassword', false));
});

it('sends a page visit to the confirm page and, once confirmed, back to the page it wanted', function () {
    $this->actingAs($this->admin);

    $this->get('/lathe/test-confirmed-page')->assertRedirect('/lathe/confirm-password');

    $this->post('/lathe/confirm-password', ['password' => 'password'])
        ->assertRedirect('/lathe/test-confirmed-page');
});

it('goes to the panel home after a page confirmation with no page to go back to', function () {
    $this->actingAs($this->admin)
        ->post('/lathe/confirm-password', ['password' => 'password'])
        ->assertRedirect('/lathe');
});

it('sends a guest to sign in', function () {
    $this->getJson('/lathe/confirmed-password-status')->assertUnauthorized();
    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertUnauthorized();
});

it('answers 404 on every confirm route once authentication routes are off', function () {
    Lathe::withoutAuthenticationRoutes();
    reloadLatheRoutes();
    $this->actingAs($this->admin);

    $this->get('/lathe/confirm-password')->assertNotFound();
    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertNotFound();
    $this->getJson('/lathe/confirmed-password-status')->assertNotFound();
});
