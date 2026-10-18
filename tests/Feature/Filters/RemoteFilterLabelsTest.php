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
    $this->getJson('/lathe/api/resources/orders')->assertOk();
    $queries = function (string $url): int {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->getJson($url)->assertOk();

        return $count;
    };

    $plain = $queries('/lathe/api/resources/orders');

    expect($this->getJson('/lathe/api/resources/orders?filter[status]=paid')->assertOk()->json('filters.labels'))->toBe([])
        ->and($queries('/lathe/api/resources/orders?filter[status]=paid'))->toBe($plain);
});
