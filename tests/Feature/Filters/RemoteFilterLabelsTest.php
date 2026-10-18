<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Customer;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    (new DemoSeeder)->forOrders(30)->run();
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('names the customers a remote filter holds, for its chip', function () {
    $customer = Customer::query()->firstOrFail();

    $labels = $this->getJson("/lathe/api/resources/orders?filter[customer]={$customer->id}")->assertOk()->json('filters.labels');

    expect($labels)->toBe(['customer' => [(string) $customer->id => $customer->name]]);
});

it('looks up no labels while no remote filter is set', function () {
    $this->getJson('/lathe/api/resources/orders?filter[status]=paid')->assertOk();

    $sql = [];
    DB::listen(function ($query) use (&$sql) {
        $sql[] = strtolower($query->sql);
    });

    expect($this->getJson('/lathe/api/resources/orders?filter[status]=paid')->assertOk()->json('filters.labels'))->toBe([]);
    expect(array_filter($sql, fn (string $query) => str_contains($query, '"customers"') && ! str_contains($query, ' in ')))->toBeEmpty();
});
