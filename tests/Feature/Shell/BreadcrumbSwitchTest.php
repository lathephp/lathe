<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('pins the edit breadcrumbs ending with Edit', function () {
    $this->get('/lathe/resources/orders/12/edit')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('breadcrumbs.0.href', '/lathe')
            ->where('breadcrumbs.1.label', 'Orders')
            ->where('breadcrumbs.2.label', '#12')
            ->where('breadcrumbs.3', ['label' => 'Edit', 'href' => null]));
});

it('sends no breadcrumbs when breadcrumbs.enabled is false', function () {
    config(['lathe.breadcrumbs.enabled' => false]);

    foreach (['/lathe/resources/orders/12', '/lathe/resources/orders/12/edit', '/lathe/resources/orders/new'] as $url) {
        $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page->where('breadcrumbs', []));
    }
});
