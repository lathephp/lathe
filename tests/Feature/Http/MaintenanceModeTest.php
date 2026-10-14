<?php

use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;

beforeEach(function () {
    // An array store keeps the app down in this process only, not in parallel test workers.
    config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
    $this->app->maintenanceMode()->activate([]);
});

it('serves the built files while the app is down', function () {
    $script = dirname(__DIR__, 2).'/Fixtures/assets/beacon.js';
    $style = sys_get_temp_dir().'/lathe-maintenance-'.getmypid().'.css';
    file_put_contents($style, 'body { color: red; }');
    Lathe::script('beacon', $script)->style('beacon', $style);

    try {
        $this->get((string) Lathe::assets()->entryUrl('/lathe'))->assertOk();
        $this->get('/lathe/modules/beacon-'.substr((string) hash_file('sha256', $script), 0, 8).'.js')->assertOk();
        $this->get('/lathe/styles/beacon-'.substr((string) hash_file('sha256', $style), 0, 8).'.css')->assertOk();
    } finally {
        unlink($style);
    }
});

it('shows the maintenance page on a panel page while the app is down', function () {
    $this->get('/lathe/login')->assertStatus(503)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Error', false)->where('status', 503)->where('shell', false));
});

it('answers an Inertia visit with the maintenance page while the app is down', function () {
    $this->get('/lathe/login', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html, application/xhtml+xml'])
        ->assertStatus(503)
        ->assertHeader('X-Inertia', 'true')
        ->assertJson(['component' => 'Error', 'props' => ['status' => 503, 'shell' => false]]);
});
