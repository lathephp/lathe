<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Resources\TrafficCop;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class BoardNullableCustomer extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->canonical(false)->board('country');
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name'), Field::select('Country')->options(['US' => 'United States', 'DE' => 'Germany'])->nullable()->inline()];
    }
}

/**
 * @return list<string>
 */
function boardSqlDuring(Closure $request): array
{
    $sql = [];
    DB::listen(function ($event) use (&$sql) {
        $sql[] = strtolower(str_replace(['"', '`'], '', $event->sql));
    });
    $request();

    return $sql;
}

/**
 * Thirty pending orders with distinct totals and the rest paid, so a column has a known size.
 */
function thirtyPending(): void
{
    Order::query()->where('status', 'pending')->update(['status' => 'paid']);

    foreach (range(0, 29) as $i) {
        Order::forceCreate(['customer_id' => 1, 'number' => sprintf('BRD-%04d', $i), 'status' => 'pending', 'total_cents' => (($i * 37) % 101 + 1) * 100, 'placed_at' => now()]);
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    (new DemoSeeder)->forOrders(30)->run();
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(BoardNullableCustomer::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('sends one page of a column in the list order, with hasMore, and the rest on page 2', function () {
    thirtyPending();
    $ids = Order::where('status', 'pending')->orderByDesc('id')->pluck('id')->all();

    $first = $this->getJson('/lathe/api/resources/orders/board?column=pending')->assertOk();
    $second = $this->getJson('/lathe/api/resources/orders/board?column=pending&page=2')->assertOk();

    expect(array_column($first->json('rows'), 'id'))->toBe(array_slice($ids, 0, 25))
        ->and($first->json('hasMore'))->toBeTrue()
        ->and(array_column($second->json('rows'), 'id'))->toBe(array_slice($ids, 25))
        ->and($second->json('hasMore'))->toBeFalse();
});

it('orders a column by the sort the list has', function (string $sort, string $direction) {
    thirtyPending();
    $expected = Order::where('status', 'pending')->orderBy('total_cents', $direction)->limit(25)->pluck('id')->all();

    $rows = $this->getJson("/lathe/api/resources/orders/board?column=pending&sort={$sort}")->assertOk()->json('rows');

    expect(array_column($rows, 'id'))->toBe($expected);
})->with([
    'ascending' => ['total_cents', 'asc'],
    'descending' => ['-total_cents', 'desc'],
]);

it('narrows the cards and the counts by a filter and by a search', function () {
    thirtyPending();
    $customer = Order::query()->where('status', 'paid')->firstOrFail()->customer_id;
    Order::forceCreate(['customer_id' => $customer, 'number' => 'BRD-9001', 'status' => 'pending', 'total_cents' => 100, 'placed_at' => now()]);
    Order::forceCreate(['customer_id' => $customer, 'number' => 'BRD-9002', 'status' => 'shipped', 'total_cents' => 100, 'placed_at' => now()]);
    $mine = Order::where('customer_id', $customer)->count();

    $cards = $this->getJson("/lathe/api/resources/orders/board?column=pending&filter[customer]={$customer}")->assertOk()->json('rows');
    $counts = $this->getJson("/lathe/api/resources/orders/board/counts?filter[customer]={$customer}")->assertOk()->json('columns');

    expect(array_column($cards, 'id'))->toBe(Order::where(['customer_id' => $customer, 'status' => 'pending'])->orderByDesc('id')->pluck('id')->all())
        ->and(array_sum(array_column(array_column($counts, 'count'), 'value')))->toBe($mine);

    $found = $this->getJson('/lathe/api/resources/orders/board?column=pending&q=BRD-9001')->assertOk()->json('rows');
    $searched = $this->getJson('/lathe/api/resources/orders/board/counts?q=BRD-9001')->assertOk()->json('columns');

    expect(array_column($found, 'id'))->toBe([Order::where('number', 'BRD-9001')->value('id')])
        ->and(array_sum(array_column(array_column($searched, 'count'), 'value')))->toBe(1);
});

it('counts each column in option order, each count one query limited to the cap plus one', function () {
    $columns = null;
    $sql = boardSqlDuring(function () use (&$columns) {
        $columns = $this->getJson('/lathe/api/resources/orders/board/counts')->assertOk()->json('columns');
    });

    expect(array_column($columns, 'value'))->toBe(['pending', 'paid', 'shipped', 'cancelled'])
        ->and(array_map(fn (array $column) => $column['count']['value'], $columns))->toBe(array_map(fn (string $status) => Order::where('status', $status)->count(), ['pending', 'paid', 'shipped', 'cancelled']))
        ->and($columns[0]['count'])->toHaveKeys(['value', 'exact', 'capped', 'estimated']);

    $counts = array_values(array_filter($sql, fn (string $query) => str_contains($query, 'lathe_count')));
    expect($counts)->toHaveCount(4);

    foreach ($counts as $query) {
        expect($query)->toContain('limit 10001');
    }
});

it('adds a last No value count and serves the cards with no value for a nullable board field', function () {
    Customer::query()->update(['country' => 'US']);
    Customer::query()->whereKey([1, 2, 3])->update(['country' => null]);

    $counts = $this->getJson('/lathe/api/resources/board-nullable-customers/board/counts')->assertOk()->json('columns');
    $rows = $this->getJson('/lathe/api/resources/board-nullable-customers/board?column=')->assertOk();

    expect(array_column($counts, 'value'))->toBe(['US', 'DE', null])
        ->and($counts[2]['count']['value'])->toBe(3)
        ->and($counts[0]['count']['value'])->toBe(Customer::where('country', 'US')->count())
        ->and(array_column($rows->json('rows'), 'id'))->toBe([3, 2, 1])
        ->and($rows->json('hasMore'))->toBeFalse();
});

it('refuses what the board cannot serve', function (string $path, int $status) {
    $this->getJson("/lathe/api/resources/{$path}")->assertStatus($status);
})->with([
    'empty column on a board that is not nullable' => ['orders/board?column=', 422],
    'missing column on a board that is not nullable' => ['orders/board', 422],
    'unknown column' => ['orders/board?column=refunded', 422],
    'unknown column on a nullable board' => ['board-nullable-customers/board?column=FR', 422],
    'page zero' => ['orders/board?column=pending&page=0', 422],
    'page not a number' => ['orders/board?column=pending&page=abc', 422],
    'resource without a board' => ['products/board?column=pending', 404],
    'counts of a resource without a board' => ['products/board/counts', 404],
]);

it('runs the same queries for page 1 and page 4 of a column of 100 cards or more', function () {
    foreach (range(1, 110) as $i) {
        Order::forceCreate(['customer_id' => $i, 'number' => sprintf('BRD-%04d', $i), 'status' => 'pending', 'total_cents' => 100, 'placed_at' => now()]);
    }
    $this->getJson('/lathe/api/resources/orders/board?column=pending')->assertOk();

    $one = boardSqlDuring(fn () => $this->getJson('/lathe/api/resources/orders/board?column=pending&page=1')->assertOk());
    $four = boardSqlDuring(fn () => $this->getJson('/lathe/api/resources/orders/board?column=pending&page=4')->assertOk());

    expect(count($four))->toBe(count($one));
});

it('gives each card the abilities and the stale-edit token of a table row', function () {
    thirtyPending();
    $rows = $this->getJson('/lathe/api/resources/orders/board?column=pending')->assertOk()->json('rows');
    $order = Order::findOrFail($rows[0]['id']);

    expect($rows[0]['abilities']['inlineEdit'])->toBeTrue()
        ->and($rows[0]['abilities']['update'])->toBeTrue()
        ->and($rows[0]['updatedAt'])->toBe(TrafficCop::token($order))
        ->and($rows[0])->not->toHaveKey('group');
});
