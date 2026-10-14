<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Policies\EmptyPolicy;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Lathe\Resources\OrderItem;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class HiddenLinkOrders extends OrderResource
{
    public static function uriKey(): string
    {
        return 'hidden-link-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->allowAll();
    }

    public function fieldsForIndex(LatheRequest $request): array
    {
        return [
            Field::text('Number'),
            Field::belongsTo('Customer')->viewable(false),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('sends the customer name with a link to the customer on the orders index', function () {
    $row = collect($this->getJson('/lathe/api/resources/orders?per_page=25')->assertOk()->json('table.rows'))->first();
    $order = Order::with('customer')->findOrFail($row['id']);

    expect($row['cells']['customer']['value'])->toBe([
        'value' => $order->customer->id,
        'display' => $order->customer->name,
        'href' => "/lathe/resources/customers/{$order->customer->id}",
    ])->and($row['cells']['customer']['display'])->toBe($order->customer->name);
});

it('still sorts the orders by the customer name', function () {
    $names = collect($this->getJson('/lathe/api/resources/orders?per_page=25&sort=customer.name')->assertOk()->json('table.rows'))
        ->map(fn (array $row) => $row['cells']['customer']['display'])
        ->all();
    $sorted = $names;
    sort($sorted);

    expect($names)->toBe($sorted);
});

it('sends no link with viewable(false)', function () {
    Lathe::registerResources(HiddenLinkOrders::class);

    $cell = $this->getJson('/lathe/api/resources/hidden-link-orders?per_page=25')->assertOk()->json('table.rows.0.cells.customer');

    expect($cell['value']['href'])->toBeNull()
        ->and($cell['display'])->not->toBe('');
});

it('sends no link when the user may not view the customer', function () {
    Gate::policy(Customer::class, EmptyPolicy::class);

    $cell = $this->getJson('/lathe/api/resources/orders?per_page=25')->assertOk()->json('table.rows.0.cells.customer');

    expect($cell['value']['href'])->toBeNull()
        ->and($cell['display'])->not->toBe('');
});

it('runs the same number of queries for 25 and for 100 orders', function () {
    $count = function (int $perPage): int {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->getJson("/lathe/api/resources/orders?per_page={$perPage}")->assertOk();
        DB::flushQueryLog();

        return $queries;
    };

    expect($count(25))->toBe($count(100));
});

it('peeks at the customer only while peeking is on and the customer has peek fields', function () {
    $column = collect($this->getJson('/lathe/api/resources/orders?per_page=25')->assertOk()->json('table.columns'))->firstWhere('key', 'customer');

    expect($column['peekable'])->toBeTrue()
        ->and(Field::belongsTo('Customer')->noPeeking()->ownedBy(OrderResource::class)->opensPeek(LatheRequest::createFrom(request())))->toBeFalse()
        ->and(Field::belongsTo('Order')->ownedBy(OrderItem::class)->opensPeek(LatheRequest::createFrom(request())))->toBeFalse();
});

it('answers the peek of a customer with its title and peek fields, and 404 for a missing one', function () {
    $json = $this->getJson('/lathe/api/resources/customers/12/peek')->assertOk()->json();

    expect($json['title'])->toBe(Customer::findOrFail(12)->name)
        ->and(array_column($json['fields'], 'key'))->toBe(['country']);

    $this->getJson('/lathe/api/resources/customers/99999999/peek')->assertNotFound();
});
