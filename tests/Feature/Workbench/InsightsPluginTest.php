<?php

namespace Lathe\Tests\Feature\Workbench;

use Illuminate\Support\Facades\DB;
use Lathe\Lathe;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('adds its header to the panel and lists Overview and Widget Gallery once each under Insights', function () {
    $response = $this->get('/lathe')->assertOk()->assertHeader('X-Lathe-Insights', '1');
    $navigation = $response->viewData('page')['props']['navigation'];
    $hrefs = collect($navigation)->pluck('items.*.href')->flatten();

    expect(collect($navigation)->firstWhere('label', 'Insights'))->toBe(['label' => 'Insights', 'icon' => null, 'items' => [
        ['label' => 'Overview', 'href' => '/lathe/plugins/insights', 'icon' => 'BarChart'],
        ['label' => 'Widget Gallery', 'href' => '/lathe/dashboards/widget-gallery', 'icon' => 'LayoutDashboard'],
    ]])
        ->and($hrefs->filter(fn (string $href) => $href === '/lathe/dashboards/widget-gallery'))->toHaveCount(1)
        ->and($hrefs->filter(fn (string $href) => $href === '/lathe/plugins/insights'))->toHaveCount(1);
});

it('puts Insights after the dashboards and before Demo Kit', function () {
    $labels = array_column($this->get('/lathe')->assertOk()->viewData('page')['props']['navigation'], 'label');

    expect(array_slice($labels, 0, 3))->toBe(['Dashboards', 'Insights', 'Demo Kit']);
});

it('counts the open orders for the Overview badge once within 60 seconds', function () {
    Order::where('id', '>', 0)->update(['status' => 'shipped']);
    Order::whereIn('id', [1, 2, 3])->update(['status' => 'pending']);
    Order::whereIn('id', [4, 5])->update(['status' => 'paid']);
    $counts = 0;
    DB::listen(function ($query) use (&$counts) {
        if (str_contains($query->sql, 'count(*)') && str_contains($query->sql, '"status" in')) {
            $counts++;
        }
    });
    $this->freezeTime();

    expect($this->get('/lathe')->viewData('page')['props']['navigationBadges'])->toBe(['/lathe/plugins/insights' => ['value' => '5', 'type' => 'info']]);

    $this->travel(59)->seconds();
    $this->get('/lathe')->assertOk();

    expect($counts)->toBe(1);
});

it('gives the page script the viewer name and the server time', function () {
    $this->travelTo('2026-03-04 05:06:07');

    expect($this->get('/lathe')->viewData('page')['props']['provided'])->toMatchArray([
        'insightsViewer' => 'Admin User',
        'insightsServerTime' => '2026-03-04T05:06:07+00:00',
    ]);
});

it('answers summary with the order counts and 404 for any other path', function () {
    Order::where('id', '>', 0)->update(['status' => 'cancelled']);
    Order::whereIn('id', [1, 2])->update(['status' => 'pending']);

    $summary = $this->getJson('/lathe/api/plugins/insights/summary')->assertOk()->json();

    expect(array_keys($summary))->toBe(['orders', 'openOrders', 'customers', 'products'])
        ->and($summary['openOrders'])->toBe(2);
    $this->getJson('/lathe/api/plugins/insights/other')->assertNotFound();
    $this->postJson('/lathe/api/plugins/insights/summary')->assertNotFound();
});

it('finds the Overview page in the palette, and nothing when the plugin is hidden from the user', function () {
    $insights = fn () => collect($this->getJson('/lathe/api/search?q=overview')->assertOk()->json('groups'))->firstWhere('resourceName', 'insights');

    expect($insights())->toBe(['resourceName' => 'insights', 'label' => 'Insights', 'results' => [[
        'resourceTitle' => 'Insights',
        'title' => 'Overview',
        'subTitle' => null,
        'resourceName' => 'insights',
        'resourceId' => 'overview',
        'avatar' => null,
        'rounded' => false,
        'url' => '/plugins/insights',
    ]]]);

    Lathe::plugins()->find('insights')?->canSee(fn () => false);

    expect($insights())->toBeNull();
});
