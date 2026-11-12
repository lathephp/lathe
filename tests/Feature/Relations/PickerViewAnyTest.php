<?php

use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
});

function pickerUser(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

it('answers 403 to support searching the Customer picker of an order form', function () {
    $this->actingAs(pickerUser('support@lathephp.com'));
    $order = Order::query()->orderBy('id')->firstOrFail();

    $this->getJson('/lathe/api/resources/orders/associatable/customer?q=ann')->assertForbidden();
    $this->getJson('/lathe/api/resources/orders/associatable/customer?page=2')->assertForbidden();
    $this->getJson("/lathe/api/resources/orders/{$order->id}/customer/associatable?q=ann")->assertForbidden();
});

it('answers 403 to support asking for the options of the Orders Customer filter', function () {
    $this->actingAs(pickerUser('support@lathephp.com'));

    $this->getJson('/lathe/api/resources/orders/filters/customer/options?q=ann&page=1')->assertForbidden();
});

it('still answers support the one saved customer of an order', function () {
    $this->actingAs(pickerUser('support@lathephp.com'));
    $order = Order::query()->orderBy('id')->firstOrFail();
    $customer = Customer::query()->findOrFail($order->customer_id);

    $json = $this->getJson("/lathe/api/resources/orders/{$order->id}/customer/associatable?current={$customer->id}")->assertOk()->json();

    expect($json['has_more'])->toBeFalse()
        ->and($json['items'])->toHaveCount(1)
        ->and($json['items'][0]['value'])->toBe($customer->id)
        ->and($json['items'][0]['display'])->toBe($customer->name);
});

it('gives the admin a full page of customers with more to follow', function () {
    $this->actingAs(pickerUser('admin@lathephp.com'));
    $order = Order::query()->orderBy('id')->firstOrFail();

    $create = $this->getJson('/lathe/api/resources/orders/associatable/customer')->assertOk()->json();
    $record = $this->getJson("/lathe/api/resources/orders/{$order->id}/customer/associatable")->assertOk()->json();

    expect($create['items'])->toHaveCount(25)
        ->and($create['has_more'])->toBeTrue()
        ->and($record['items'])->toHaveCount(25)
        ->and($record['has_more'])->toBeTrue();
});

it('lets support edit an order and read its customer, but not search for another', function () {
    $this->actingAs(pickerUser('support@lathephp.com'));
    $order = Order::query()->orderBy('id')->firstOrFail();
    $name = DB::table('customers')->where('id', $order->customer_id)->value('name');

    $customer = collect($this->getJson("/lathe/api/resources/orders/{$order->id}/update-fields")->assertOk()->json('fields'))
        ->keyBy('attribute')['customer'];
    $saved = $this->getJson("/lathe/api/resources/orders/{$order->id}/customer/associatable?current={$order->customer_id}")->assertOk();

    expect($customer['value']['value'])->toBe($order->customer_id)
        ->and($customer['value']['display'])->toBe($name)
        ->and($saved->json('items.0.display'))->toBe($name);

    $this->getJson("/lathe/api/resources/orders/{$order->id}/customer/associatable?q=a")->assertForbidden();
});
