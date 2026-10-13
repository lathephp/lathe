<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * The SQL of one Orders search, and the order ids it lists.
 *
 * @return array{0: list<string>, 1: list<int>}
 */
function ordersSearch(string $term): array
{
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = strtolower($event->sql);
    });
    $ids = array_column(test()->getJson('/lathe/api/resources/orders?per_page=100&q='.urlencode($term))->assertOk()->json('table.rows'), 'id');

    return [$queries, $ids];
}

it('looks up the matching customers first, so a large orders table keeps its indexes', function () {
    [$queries, $ids] = ordersSearch('Annette');
    $expected = Order::whereIn('customer_id', Customer::where('name', 'like', 'Annette%')->pluck('id'))->orderByDesc('id')->limit(100)->pluck('id')->all();

    $orders = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'from "orders"') || str_contains($sql, 'from `orders`')));

    expect($ids)->not->toBeEmpty()->toBe($expected)
        ->and(implode("\n", $orders))->not->toContain('exists');
});

it('falls back to an exists subquery when more customers match than the id list cap', function () {
    config(['lathe.limits.id_list_max' => 2]);
    [$queries, $ids] = ordersSearch('Ann');
    $expected = Order::whereIn('customer_id', Customer::where('name', 'like', 'Ann%')->pluck('id'))->orderByDesc('id')->limit(100)->pluck('id')->all();

    expect($ids)->toBe($expected)
        ->and(implode("\n", $queries))->toContain('exists');
});
