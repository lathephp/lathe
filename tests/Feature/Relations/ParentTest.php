<?php

namespace Lathe\Tests\Feature\Relations;

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Lathe\Resources\OrderItem;
use Workbench\App\Models\OrderItem as OrderItemModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('declares the order item as a child of the order items relation', function () {
    expect(OrderItem::make()->definition()->getParent())->toBe([Order::class, 'items']);
});

it('leads the breadcrumbs of a child through its parent', function () {
    $item = OrderItemModel::query()->forceCreate(['order_id' => 12, 'product_id' => 1, 'quantity' => 2, 'price_cents' => 500]);

    $this->get('/lathe/resources/order-items/'.$item->getKey())
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Detail', false)
            ->where('breadcrumbs', [
                ['label' => 'Dashboard', 'href' => '/lathe'],
                ['label' => 'Orders', 'href' => '/lathe/resources/orders'],
                ['label' => 'ORD-00099454', 'href' => '/lathe/resources/orders/12'],
                ['label' => 'Items', 'href' => null],
                ['label' => (string) $item->getKey(), 'href' => null],
            ]));
});

it('keeps the plain breadcrumbs for a Resource without a parent', function () {
    $this->get('/lathe/resources/customers/12')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('breadcrumbs', [
                ['label' => 'Dashboard', 'href' => '/lathe'],
                ['label' => 'Customers', 'href' => '/lathe/resources/customers'],
                ['label' => 'Douglas Monahan', 'href' => null],
            ]));
});
