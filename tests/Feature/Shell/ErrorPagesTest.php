<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Lathe\Tests\Fixtures\Policies\EmptyPolicy;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
    $this->guest = User::forceCreate(['name' => 'Guest User', 'email' => 'guest@lathephp.com', 'password' => bcrypt('password')]);
});

function errorPage(int $status, bool $shell): Closure
{
    return fn (AssertableInertia $page) => $page->component('Error', false)->where('status', $status)->where('shell', $shell);
}

it('shows 404 inside the shell when signed in, full screen when not', function (string $url) {
    $this->actingAs($this->admin)->get($url)->assertNotFound()->assertInertia(errorPage(404, true));
})->with(['/lathe/nothing', '/lathe/resources/nothing', '/lathe/deep/nothing/here']);

it('shows 404 full screen when signed out', function () {
    auth()->logout();
    $this->get('/lathe/nothing')->assertNotFound()->assertInertia(errorPage(404, false));
});

it('leaves a host 404 alone', function () {
    $response = $this->get('/no-such-host-page')->assertNotFound();

    expect($response->headers->get('X-Inertia'))->toBeNull()
        ->and($response->getContent())->not->toContain('data-page');
});

it('shows 403 inside the shell for a user who fails viewLathe', function () {
    $this->actingAs($this->guest)->get('/lathe')->assertForbidden()->assertInertia(errorPage(403, true));
    $this->actingAs($this->guest)->getJson('/lathe/api/resources/orders')->assertForbidden()->assertJsonStructure(['message']);
});

it('passes the reason of a Resource refusal to the page', function () {
    Gate::policy(Order::class, EmptyPolicy::class);
    Gate::getPolicyFor(Order::class);

    $this->actingAs($this->admin)->get('/lathe/resources/orders')->assertForbidden()->assertInertia(errorPage(403, true));
});

it('names allowAll() on the 403 page of a Resource with no policy', function () {
    Gate::guessPolicyNamesUsing(fn () => []);

    $this->actingAs($this->admin)->get('/lathe/resources/orders')->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('message', fn (string $message) => str_contains($message, '->allowAll()')));
});

it('shows the session-expired page for a 419', function () {
    Route::middleware('lathe')->get('/lathe/expired', fn () => abort(419));
    $this->actingAs($this->admin)->get('/lathe/expired')->assertStatus(419)->assertInertia(errorPage(419, true));
});

it('renders 500 and 503 inside the shell when debug is off', function (int $status) {
    config(['app.debug' => false]);
    Route::middleware('lathe')->get("/lathe/broken-{$status}", fn () => $status === 500 ? throw new RuntimeException('boom') : abort(503));

    $this->actingAs($this->admin)->get("/lathe/broken-{$status}")->assertStatus($status)->assertInertia(errorPage($status, true));
})->with([500, 503]);
