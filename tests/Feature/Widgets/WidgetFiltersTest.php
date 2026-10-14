<?php

use Illuminate\Support\Facades\DB;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Resources\FilteredWidgetOrder;
use Lathe\Tests\Fixtures\Widgets\FilteredOrders;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Cards::$widgets = [new FilteredOrders];
    Lathe::registerDashboards(Cards::class);
    Lathe::registerResources(FilteredWidgetOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('counts only the rows the index filters keep on a Resource card', function () {
    $pending = DB::table('orders')->where('status', 'pending')->count();
    $url = '/lathe/api/resources/filtered-widget-orders/widgets/filtered-orders?range=ALL';

    expect($pending)->toBeGreaterThan(0)->toBeLessThan(10000)
        ->and($this->getJson($url.'&filter[status]=pending')->assertOk()->json('value.value'))->toBe($pending)
        ->and($this->getJson($url)->assertOk()->json('value.value'))->toBe(10000);
});

it('counts only the rows the filters keep on a lens card', function () {
    $pending = DB::table('orders')->where('status', 'pending')->count();
    $url = '/lathe/api/resources/filtered-widget-orders/lenses/filtered-pending-orders/widgets/filtered-orders?range=ALL';

    expect($this->getJson($url.'&filter[status]=pending')->assertOk()->json('value.value'))->toBe($pending);
});

it('keeps the cached count of one filter from answering another', function () {
    $url = '/lathe/api/resources/filtered-widget-orders/widgets/filtered-orders?range=ALL';
    $paid = DB::table('orders')->where('status', 'paid')->count();

    $this->getJson($url.'&filter[status]=pending')->assertOk();

    expect($this->getJson($url.'&filter[status]=paid')->assertOk()->json('value.value'))->toBe($paid);
});

it('refuses a filter the host does not offer', function () {
    $this->getJson('/lathe/api/resources/filtered-widget-orders/widgets/filtered-orders?filter[nope]=1')->assertStatus(422);
});

it('ignores filters on a dashboard card', function () {
    expect($this->getJson('/lathe/api/dashboards/cards/widgets/filtered-orders?range=ALL&filter[status]=pending')->assertOk()->json('value.value'))->toBe(10000);
});
