<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Resources\ScopedWidgetOrder;
use Lathe\Tests\Fixtures\Widgets\ProgressProbe;
use Lathe\Tests\Fixtures\Widgets\ScopeRequired;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Lathe\Widgets\WidgetCache;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Cards::$widgets = [new ValueOrders, new ScopeRequired];
    ProgressProbe::$using = null;
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

it('scopes a progress card on a Resource and flushes it with the table it counts', function () {
    ProgressProbe::$using = fn (LatheRequest $request) => $this->count($request, Order::class, fn (Builder $query) => $query->where('status', 'paid'));
    $url = '/lathe/api/resources/scoped-widget-orders/widgets/progress-probe';

    expect($this->getJson($url)->assertOk()->json('value'))->toMatchArray([
        'value' => DB::table('orders')->where('status', 'paid')->count(),
        'target' => DB::table('orders')->where('status', '!=', 'cancelled')->count(),
    ]);

    app(WidgetCache::class)->flushTables(['orders']);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson($url)->assertOk();

    expect(DB::getQueryLog())->toHaveCount(2);
});
