<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Testing\LatheTest;
use Workbench\App\Lathe\Resources\OrderItem as OrderItemResource;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('reads the rows through a parent relation', function () {
    $order = Order::findOrFail(12);
    $query = (new ResourceQuery(OrderItemResource::make(), LatheRequest::createFrom(request()), QueryContext::Index))->forRelation($order->items());

    expect($query->rows()->pluck('id')->sort()->values()->all())
        ->toBe(OrderItem::where('order_id', 12)->orderBy('id')->pluck('id')->all())
        ->and($query->rows()->pluck('order_id')->unique()->all())->toBe([12]);
});

it('sends a belongsTo value as the related key and title', function () {
    $order = Order::findOrFail(12);
    $customer = Customer::findOrFail($order->customer_id);

    $json = LatheTest::resource('orders')->updateFields(12)->assertOk()->json();
    $field = collect($json['fields'])->firstWhere('key', 'customer');

    expect($field['value'])->toBe(['value' => $customer->id, 'display' => $customer->name, 'href' => "/lathe/resources/customers/{$customer->id}"])
        ->and($field)->toMatchArray(['kind' => 'belongsTo', 'relationship' => 'customer', 'resource' => 'customers']);
});

it('writes the foreign key from the form value', function () {
    $order = Order::findOrFail(12);
    $other = Customer::whereKeyNot($order->customer_id)->firstOrFail();

    LatheTest::resource('orders')->update(12, [
        'number' => $order->number,
        'status' => $order->status,
        'placed_at' => $order->placed_at->toIso8601String(),
        'total_cents' => $order->total_cents,
        'customer' => ['value' => $other->id],
    ])->assertOk();

    expect(Order::findOrFail(12)->customer_id)->toBe($other->id);
});
