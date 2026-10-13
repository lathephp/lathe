<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Lathe\Events\ServingLathe;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

it('stamps every panel response with the version and asset hash', function () {
    $this->getJson('/lathe/api/nothing')
        ->assertNotFound()
        ->assertHeader('X-Lathe-Version', Lathe::version())
        ->assertHeader('X-Lathe-Assets', Lathe::assets()->hash());

    $this->get('/lathe')
        ->assertHeader('X-Lathe-Version', Lathe::version());
});

it('leaves host routes alone', function () {
    Route::get('/host-page', fn () => 'host');
    Event::fake([ServingLathe::class]);

    $this->get('/host-page')->assertOk()->assertHeaderMissing('X-Lathe-Version');
    $this->get('/lathe-like')->assertHeaderMissing('X-Lathe-Version');

    Event::assertNotDispatched(ServingLathe::class);
});

it('fires ServingLathe once per panel request', function () {
    Event::fake([ServingLathe::class]);

    $this->getJson('/lathe/api/nothing');
    Event::assertDispatchedTimes(ServingLathe::class, 1);

    $this->getJson('/lathe/api/nothing');
    Event::assertDispatchedTimes(ServingLathe::class, 2);
});

it('binds a LatheRequest only while a panel request runs', function () {
    $seen = [];
    Event::listen(ServingLathe::class, function () use (&$seen) {
        $seen[] = app(LatheRequest::class)->path();
    });

    expect(app()->bound(LatheRequest::class))->toBeFalse();

    $this->getJson('/lathe/api/first');
    $this->getJson('/lathe/api/second');

    expect($seen)->toBe(['lathe/api/first', 'lathe/api/second'])
        ->and(app()->resolved(LatheRequest::class) && app()->bound(LatheRequest::class))->toBeFalse();
});

it('answers an unknown panel URL with 404, never 500', function () {
    $this->getJson('/lathe/api/resources/nothing/at/all')->assertNotFound();
    $this->getJson('/lathe/nothing')->assertNotFound();
});

it('serves the panel under a custom path', function () {
    config(['lathe.path' => '/admin']);
    reloadLatheRoutes();

    expect(route('lathe.dashboard', absolute: false))->toBe('/admin');
    $this->get('/admin')->assertHeader('X-Lathe-Version', Lathe::version());
    $this->get('/lathe')->assertNotFound()->assertHeaderMissing('X-Lathe-Version');
});

it('answers 404 on every other host when a domain is set', function () {
    config(['lathe.domain' => 'panel.example.test']);
    reloadLatheRoutes();

    $this->get('http://other.example.test/lathe')->assertNotFound();
    expect($this->get('http://panel.example.test/lathe')->status())->not->toBe(404);
});
