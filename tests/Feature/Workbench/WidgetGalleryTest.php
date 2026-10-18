<?php

use Inertia\Testing\AssertableInertia;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('shows one card of each of the 11 widget types', function () {
    $components = $this->getJson('/lathe/api/dashboards/widget-gallery/widgets')->assertOk()->json('widgets.*.component');
    sort($components);

    expect($components)->toBe([
        'bar-widget', 'custom-widget', 'help-widget', 'partition-widget', 'pie-widget', 'progress-widget',
        'radar-widget', 'radial-widget', 'table-widget', 'trend-widget', 'value-widget',
    ]);
});

it('fills each gallery card from the demo store', function () {
    $value = fn (string $widget) => $this->getJson("/lathe/api/dashboards/widget-gallery/widgets/{$widget}")->assertOk()->json();
    $paid = Order::where('status', 'paid')->count();
    $shipped = Order::where('status', 'shipped')->count();

    expect($value('orders'))->toMatchArray(['name' => 'Orders', 'component' => 'value-widget'])
        ->and($value('orders')['value']['change'])->toBeGreaterThan(0)
        ->and($value('orders-per-day')['name'])->toBe('Orders per day')
        ->and($value('orders-per-day')['value']['trend'])->toHaveCount(30)
        ->and($value('paid-orders'))->toMatchArray(['name' => 'Paid orders'])
        ->and($value('paid-orders')['value'])->toMatchArray(['value' => $paid, 'target' => 10000, 'percentage' => round($paid / 100, 2)])
        ->and($value('order-completion'))->toMatchArray(['name' => 'Order completion'])
        ->and($value('order-completion')['value'])->toMatchArray(['value' => $shipped, 'target' => 10000]);

    $rows = $value('top-customers')['value']['rows'];
    expect($value('top-customers')['name'])->toBe('Top customers')
        ->and($rows)->toHaveCount(5)
        ->and(array_column($rows, 'icon'))->toBe(['user', 'user', 'user', 'user', 'user'])
        ->and(array_filter(array_column($rows, 'subtitle'), fn (string $subtitle) => preg_match('/^\d+ orders, \$[\d,]+\.\d\d$/', $subtitle) === 1))->toHaveCount(5);

    $bars = $value('orders-by-status');
    expect($bars['name'])->toBe('Orders by status')
        ->and(collect($bars['value']['bars'])->pluck('label')->sort()->values()->all())->toBe(['Cancelled', 'Paid', 'Pending', 'Shipped'])
        ->and(collect($bars['value']['bars'])->sum('value'))->toBe(Order::where('placed_at', '>=', now()->subDays(30))->count());

    $pie = $value('products-by-category');
    expect($pie['name'])->toBe('Products by category')
        ->and($pie['value']['value'])->toHaveCount(20)
        ->and(collect($pie['value']['value'])->every(fn (array $slice) => str_starts_with($slice['label'], 'Category ')))->toBeTrue()
        ->and(collect($pie['value']['value'])->sum('value'))->toBe(500);

    $countries = $value('customers-by-country');
    expect($countries['name'])->toBe('Customers by country')
        ->and(collect($countries['value']['value'])->pluck('label')->sort()->values()->all())->toBe(['AU', 'BR', 'CA', 'DE', 'FR', 'GB', 'JP', 'NL', 'SE', 'US'])
        ->and(collect($countries['value']['value'])->sum('value'))->toBe(1000);

    $radar = $value('sales-by-weekday');
    expect($radar['name'])->toBe('Sales by weekday')
        ->and(array_column($radar['value']['axes'], 'label'))->toBe(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
        ->and(array_sum($radar['value']['datasets'][0]['data']))->toEqualWithDelta(Order::sum('total_cents') / 100, 4);
});

it('shows the help text and names the clock component, with no value request', function () {
    $widgets = collect($this->getJson('/lathe/api/dashboards/widget-gallery/widgets')->json('widgets'))->keyBy('component');

    expect($widgets['custom-widget'])->toMatchArray(['name' => 'Clock', 'clientComponent' => 'workbench-clock', 'requestsValue' => false])
        ->and($widgets['help-widget'])->toMatchArray(['name' => 'Help', 'requestsValue' => false])
        ->and($widgets['help-widget']['content'])->toContain('11 widget types');
});

it('changes the Sales Orders number and its change for 60 days', function () {
    $thirty = $this->getJson('/lathe/api/dashboards/main/widgets/orders')->assertOk()->json('value');
    $sixty = $this->getJson('/lathe/api/dashboards/main/widgets/orders?range=60')->assertOk()->json('value');

    expect(collect($this->getJson('/lathe/api/dashboards/main/widgets')->json('widgets'))->pluck('uriKey')->all())->toContain('orders', 'revenue', 'paid-orders', 'orders-per-day', 'orders-by-status')
        ->and($sixty['value'])->not->toBe($thirty['value'])
        ->and($sixty['change'])->not->toBe($thirty['change'])
        ->and($thirty['change'])->toBeGreaterThan(0);
});

it('paints the gallery in one visit with 11 descriptors and a deferred group per value card', function () {
    // The partial reload asserts the page file again; the page tests run without a build.
    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->get('/lathe/dashboards/widget-gallery')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard', false)
        ->where('dashboard.name', 'Widget Gallery')
        ->where('dashboard.showRefreshButton', true)
        ->has('widgets', 11)
        ->missing('widgets.0.value')
        ->missing('widgetValue:orders'));

    // The help and clock cards ask for no value, so 9 of the 11 cards defer one.
    expect(array_keys($this->get('/lathe/dashboards/widget-gallery')->viewData('page')['deferredProps']))->toBe([
        'orders', 'orders-per-day', 'paid-orders', 'top-customers', 'orders-by-status', 'products-by-category',
        'customers-by-country', 'sales-by-weekday', 'order-completion',
    ]);
});
