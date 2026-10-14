<?php

use Inertia\Testing\AssertableInertia;
use Lathe\Exceptions\LatheException;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Resources\WidgetCustomer;
use Lathe\Tests\Fixtures\Resources\WidgetOrder;
use Lathe\Tests\Fixtures\Widgets\AttributedCard;
use Lathe\Tests\Fixtures\Widgets\DetailOnly;
use Lathe\Tests\Fixtures\Widgets\FluentCard;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Lathe\Tests\Fixtures\Widgets\Twin;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Cards::$widgets = null;
    OrderCount::$requests = [];
    Lathe::registerDashboards(Cards::class);
    Lathe::registerResources(WidgetOrder::class, WidgetCustomer::class);
});

function widgetAdmin(): User
{
    return User::where('email', 'admin@lathephp.com')->firstOrFail();
}

dataset('widget calls', [
    'dashboard list' => ['/lathe/api/dashboards/cards/widgets', '/lathe/api/dashboards/nope/widgets', null],
    'dashboard value' => ['/lathe/api/dashboards/cards/widgets/order-count', '/lathe/api/dashboards/cards/widgets/nope', '/lathe/api/dashboards/cards/widgets/secret'],
    'resource list' => ['/lathe/api/resources/widget-orders/widgets', '/lathe/api/resources/nope/widgets', null],
    'resource value' => ['/lathe/api/resources/widget-orders/widgets/order-count', '/lathe/api/resources/widget-orders/widgets/nope', '/lathe/api/resources/widget-orders/widgets/secret'],
    'lens list' => ['/lathe/api/resources/widget-orders/lenses/counted-orders/widgets', '/lathe/api/resources/widget-orders/lenses/nope/widgets', null],
    'lens value' => ['/lathe/api/resources/widget-orders/lenses/counted-orders/widgets/order-count', '/lathe/api/resources/widget-orders/lenses/counted-orders/widgets/nope', '/lathe/api/resources/widget-orders/lenses/counted-orders/widgets/secret'],
]);

it('answers 200, 404, 403 and 401 for each widget call', function (string $known, string $unknown, ?string $hidden) {
    $this->getJson($known)->assertUnauthorized();

    $this->actingAs(widgetAdmin());
    $this->getJson($known)->assertOk();
    $this->getJson($unknown)->assertNotFound();

    if ($hidden !== null) {
        $this->getJson($hidden)->assertForbidden();
    }
})->with('widget calls');

it('answers 403 for a widget whose policy has no viewWidget, and leaves it off the list', function () {
    $this->actingAs(widgetAdmin());

    $this->getJson('/lathe/api/resources/widget-customers/widgets/order-count')->assertForbidden();
    $this->getJson('/lathe/api/resources/widget-customers/widgets')->assertOk()->assertJsonCount(0, 'widgets');
});

it('hidden_widget_returns_403', function () {
    $this->actingAs(widgetAdmin())->getJson('/lathe/api/dashboards/cards/widgets/secret')->assertForbidden();
})->group('regression');

it('hidden_dashboard_returns_403', function () {
    Lathe::dashboards()->flush();
    Lathe::registerDashboards(Cards::make()->canSee(fn () => false));

    $this->actingAs(widgetAdmin());
    $this->getJson('/lathe/api/dashboards/cards/widgets')->assertForbidden();
    $this->getJson('/lathe/api/dashboards/cards/widgets/order-count')->assertForbidden();
})->group('regression');

it('answers every value call as name, uriKey, component and value', function () {
    $this->actingAs(widgetAdmin())->getJson('/lathe/api/dashboards/cards/widgets/order-count')->assertOk()->assertExactJson([
        'name' => 'Order Count',
        'uriKey' => 'order-count',
        'component' => 'order-count',
        'value' => Order::count(),
    ]);
});

it('never takes the timezone from the request', function () {
    $this->actingAs(widgetAdmin());

    $plain = $this->getJson('/lathe/api/dashboards/cards/widgets/order-count')->json('value');
    $zoned = $this->getJson('/lathe/api/dashboards/cards/widgets/order-count?timezone=Pacific/Kiritimati')->json('value');

    expect($zoned)->toBe($plain);
});

it('lists the widgets the user may see, without their values', function () {
    $this->actingAs(widgetAdmin())->getJson('/lathe/api/dashboards/cards/widgets')->assertOk()
        ->assertJsonCount(1, 'widgets')
        ->assertJsonPath('widgets.0.uriKey', 'order-count')
        ->assertJsonMissingPath('widgets.0.value');
});

it('keeps detail-only widgets off the index and needs a resourceId for their value', function () {
    $order = Order::firstOrFail();
    $this->actingAs(widgetAdmin());

    expect($this->getJson('/lathe/api/resources/widget-orders/widgets')->json('widgets.*.uriKey'))->toBe(['order-count'])
        ->and($this->getJson("/lathe/api/resources/widget-orders/widgets?resourceId={$order->getKey()}")->json('widgets.*.uriKey'))->toBe(['order-count', 'detail-only']);

    $this->getJson('/lathe/api/resources/widget-orders/widgets/detail-only')->assertNotFound();
    $this->getJson("/lathe/api/resources/widget-orders/widgets/detail-only?resourceId={$order->getKey()}")->assertOk()
        ->assertJsonPath('value', 'detail '.$order->getKey());
    $this->getJson('/lathe/api/resources/widget-orders/widgets/detail-only?resourceId=999999')->assertNotFound();
});

it('fails when two widgets of one list share a key, naming both classes', function () {
    Cards::$widgets = [new OrderCount, new Twin];

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs(widgetAdmin())->getJson('/lathe/api/dashboards/cards/widgets'))
        ->toThrow(LatheException::class, Twin::class);
});

it('gives the widget attribute twins the descriptor of the fluent calls', function () {
    $descriptor = fn ($widget) => collect($widget->descriptor())->except(['name', 'uriKey', 'component'])->all();

    expect($descriptor(new AttributedCard))->toBe($descriptor(new FluentCard))
        ->and((new AttributedCard)->cacheFor())->toBe(30)
        ->and($descriptor(new AttributedCard))->toMatchArray(['width' => '1/2', 'columns' => 6, 'refreshEvery' => 10]);

    expect((new DetailOnly)->isOnlyOnDetail())->toBeTrue();
});

it('refuses a refresh under 5 seconds and detail-only widgets that refresh on filters', function () {
    expect(fn () => OrderCount::make()->refreshEvery(4))->toThrow(LogicException::class)
        ->and(OrderCount::make()->refreshEvery(5)->descriptor()['refreshEvery'])->toBe(5)
        ->and(fn () => OrderCount::make()->onlyOnDetail()->refreshWhenFiltersChange())->toThrow(LogicException::class)
        ->and(fn () => OrderCount::make()->refreshWhenFiltersChange()->onlyOnDetail())->toThrow(LogicException::class);
});

it('paints a dashboard in one visit with descriptors and one deferred group per card', function () {
    Cards::$widgets = [new OrderCount, AttributedCard::make()];
    // The partial reload asserts the page file again; the page tests run without a build.
    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->actingAs(widgetAdmin())->get('/lathe/dashboards/cards')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard', false)
        ->has('widgets', 2)
        ->where('widgets.0.uriKey', 'order-count')
        ->missing('widgets.0.value')
        ->missing('widgetValue:order-count')
        ->loadDeferredProps(['order-count'], fn (AssertableInertia $reload) => $reload
            ->where('widgetValue:order-count.value', Order::count())
            ->missing('widgetValue:attributed-card')));

    expect($this->get('/lathe/dashboards/cards')->viewData('page')['deferredProps'])
        ->toBe(['order-count' => ['widgetValue:order-count'], 'attributed-card' => ['widgetValue:attributed-card']]);
});
