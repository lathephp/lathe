<?php

use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Resources\WidgetCustomer;
use Lathe\Tests\Fixtures\Resources\WidgetOrder;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    OrderCount::$requests = [];
    Lathe::registerResources(WidgetOrder::class, WidgetCustomer::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('lists the index cards the user may see as a page prop, without detail-only cards', function () {
    $this->get('/lathe/resources/widget-orders')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Resources/Index', false)
        ->where('widgets.0', (new OrderCount)->descriptor())
        ->where('widgets', fn ($widgets) => array_column($widgets->all(), 'uriKey') === ['order-count']));
});

it('lists the detail cards, detail-only ones included, as a page prop', function () {
    $order = Order::firstOrFail();

    $this->get("/lathe/resources/widget-orders/{$order->getKey()}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Resources/Detail', false)
        ->where('widgets', fn ($widgets) => array_column($widgets->all(), 'uriKey') === ['order-count', 'detail-only']));
});

it('lists the lens cards the user may see as a page prop', function () {
    $this->get('/lathe/resources/widget-orders/lens/counted-orders')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Resources/Index', false)
        ->where('widgets', fn ($widgets) => array_column($widgets->all(), 'uriKey') === ['order-count']));
});

it('leaves out the cards the policy does not let the user view', function () {
    $this->get('/lathe/resources/widget-customers')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('widgets', []));
});

it('still computes no card value while it paints the page', function () {
    $this->get('/lathe/resources/widget-orders')->assertOk();

    expect(OrderCount::$requests)->toBe([]);
});
