<?php

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    (new DemoSeeder)->forOrders(30)->run();
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('picks an order card from the index columns: the status badge, the total and the customer', function () {
    expect($this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.phoneCard'))
        ->toBe(['title' => null, 'badge' => 'status', 'value' => 'total_cents', 'meta' => 'customer']);
});

it('takes the fields phoneCard() names and picks the rest', function () {
    expect($this->getJson('/lathe/api/resources/products')->assertOk()->json('table.phoneCard'))
        ->toBe(['title' => 'name', 'badge' => null, 'value' => 'price_cents', 'meta' => 'category']);
});
