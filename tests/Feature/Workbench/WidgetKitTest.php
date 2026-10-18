<?php

use Lathe\Lathe;
use Lathe\Testing\LatheTest;
use Lathe\Tests\Fixtures\Resources\WidgetOrder;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Lathe::registerResources(WidgetOrder::class);
    LatheTest::actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('lists the dashboards and shows one', function () {
    expect(LatheTest::dashboards()->assertOk()->json('dashboards.*.uriKey'))->toBe(['main', 'widget-gallery'])
        ->and(LatheTest::dashboard('widget-gallery')->show()->assertOk()->json('dashboard.name'))->toBe('Widget Gallery');

    LatheTest::dashboard('nope')->show()->assertNotFound();
});

it('lists the widgets of a dashboard and reads one card with its range', function () {
    $sales = LatheTest::dashboard('main');

    expect($sales->widgets()->assertOk()->json('widgets.*.uriKey'))->toContain('orders', 'revenue', 'paid-orders')
        ->and($sales->widget('paid-orders')->assertOk()->json('value.value'))->toBe(Order::where('status', 'paid')->count())
        ->and($sales->widget('orders', ['range' => 60])->json('value'))->toBe($this->getJson('/lathe/api/dashboards/main/widgets/orders?range=60')->json('value'))
        ->and($sales->widget('orders', ['range' => 60])->json('value.value'))->not->toBe($sales->widget('orders')->json('value.value'));
});

it('lists the widgets of a Resource and reads one, on the index and on a detail page', function () {
    $order = Order::firstOrFail();
    $orders = LatheTest::resource(WidgetOrder::class);

    expect($orders->widgets()->assertOk()->json('widgets.*.uriKey'))->toBe(['order-count'])
        ->and($orders->widgets(['resourceId' => $order->getKey()])->json('widgets.*.uriKey'))->toBe(['order-count', 'detail-only'])
        ->and($orders->widget('order-count')->assertOk()->json('value'))->toBe(10000)
        ->and($orders->widget('detail-only', ['resourceId' => $order->getKey()])->assertOk()->json('value'))->toBe('detail '.$order->getKey());

    $orders->widget('secret')->assertForbidden();
});

it('lists the widgets of a lens and reads one', function () {
    $lens = LatheTest::resource('widget-orders')->lens('counted-orders');

    expect($lens->widgets()->assertOk()->json('widgets.*.uriKey'))->toBe(['order-count'])
        ->and($lens->widget('order-count')->assertOk()->json('uriKey'))->toBe('order-count');

    $lens->widget('secret')->assertForbidden();
    LatheTest::resource('widget-orders')->lens('nope')->widgets()->assertNotFound();
    LatheTest::resource('widget-orders')->lens('nope')->widget('order-count')->assertNotFound();
});
