<?php

use Illuminate\Support\Facades\DB;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Resources\ScopedWidgetOrder;
use Lathe\Tests\Fixtures\Widgets\ScopeRequired;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Cards::$widgets = [new ValueOrders, new ScopeRequired];
    Lathe::registerDashboards(Cards::class);
    Lathe::registerResources(ScopedWidgetOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * The number a value card shows over all time.
 */
function allTimeValue(string $url): mixed
{
    return test()->getJson($url.'?range=ALL')->assertOk()->json('value.value');
}

it('resource_widget_is_row_scoped', function () {
    $cancelled = DB::table('orders')->where('status', 'cancelled')->count();

    expect($cancelled)->toBeGreaterThan(0)
        ->and(allTimeValue('/lathe/api/resources/scoped-widget-orders/widgets/value-orders'))->toBe(10000 - $cancelled);
})->group('regression');

it('lens_widget_is_row_scoped', function () {
    $pending = DB::table('orders')->where('status', 'pending')->count();

    expect($pending)->toBeGreaterThan(0)
        ->and(allTimeValue('/lathe/api/resources/scoped-widget-orders/lenses/scoped-pending-orders/widgets/value-orders'))->toBe($pending);
})->group('regression');

it('dashboard_widget_is_not_scoped', function () {
    expect(allTimeValue('/lathe/api/dashboards/cards/widgets/value-orders'))->toBe(10000);
})->group('regression');

it('pins a query over another model as unscoped on a Resource', function () {
    expect(allTimeValue('/lathe/api/resources/scoped-widget-orders/widgets/value-customers'))->toBe(1000);
});

it('fails the value request of a card that requires a host scope on a dashboard', function () {
    $this->getJson('/lathe/api/dashboards/cards/widgets/scope-required')->assertStatus(500)->assertJsonStructure(['message']);
});
