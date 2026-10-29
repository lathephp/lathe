<?php

use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Customer as CustomerResource;
use Workbench\App\Lathe\Resources\OrderItem as OrderItemResource;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class ThroughCustomer extends Customer
{
    protected $table = 'customers';

    /**
     * @return HasManyThrough<OrderItem, Order, $this>
     */
    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, Order::class, 'customer_id', 'order_id');
    }

    /**
     * @return HasOneThrough<OrderItem, Order, $this>
     */
    public function firstItem(): HasOneThrough
    {
        return $this->hasOneThrough(OrderItem::class, Order::class, 'customer_id', 'order_id')->oldest('order_items.id');
    }
}

class ThroughCustomers extends CustomerResource
{
    public static function uriKey(): string
    {
        return 'through-customers';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->model(ThroughCustomer::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name'),
            Field::hasManyThrough('Order Items', 'orderItems', OrderItemResource::class),
            Field::hasOneThrough('First Item', 'firstItem', OrderItemResource::class),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

function customerIndexQueries(int $perPage): int
{
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });
    test()->getJson("/lathe/api/resources/customers?per_page={$perPage}")->assertOk();

    return $count;
}

test('relation_counts_are_preloaded', function () {
    $rows = collect($this->getJson('/lathe/api/resources/customers?per_page=25')->assertOk()->json('table.rows'));
    $first = $rows->first();

    expect($first['cells']['orders']['value'])->toBe(Order::where('customer_id', $first['id'])->count());

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $this->getJson('/lathe/api/resources/customers?per_page=100')->assertOk();
    $counts = array_filter($queries, fn (string $sql) => str_contains(strtolower($sql), 'count(') && str_contains($sql, 'orders'));

    expect($counts)->toHaveCount(1)
        ->and(customerIndexQueries(25))->toBe(customerIndexQueries(100));
})->group('regression');

it('lists a through relation read-only, with no create', function () {
    Lathe::registerResources(ThroughCustomers::class);
    $order = Order::findOrFail(12);
    $expected = OrderItem::whereIn('order_id', Order::where('customer_id', $order->customer_id)->pluck('id'))->orderBy('id')->pluck('id')->all();

    $json = $this->getJson("/lathe/api/resources/through-customers/{$order->customer_id}/orderItems?per_page=100")->assertOk()->json();

    expect(collect($json['rows'])->pluck('related_id')->sort()->values()->all())->toBe($expected)
        ->and(Field::hasManyThrough('Order Items')->createsThroughPanel())->toBeFalse()
        ->and(Field::hasManyThrough('Order Items')->attaches())->toBeFalse();

    $first = $this->getJson("/lathe/api/resources/through-customers/{$order->customer_id}/firstItem")->assertOk()->json('rows');

    expect($first)->toHaveCount(1);
});

it('offers a one-through panel the page size it answers', function () {
    Lathe::registerResources(ThroughCustomers::class);
    $order = Order::findOrFail(12);

    $json = $this->getJson("/lathe/api/resources/through-customers/{$order->customer_id}/firstItem")->assertOk()->json();

    expect($json['perPage'])->toBe(1)
        ->and($json['perPageOptions'])->toBe([1])
        ->and($json['rows'])->toHaveCount(1);
});
