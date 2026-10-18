<?php

use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Widgets\BigBars;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * The lines of a CSV download.
 *
 * @return list<string>
 */
function csvLines(string $csv): array
{
    return explode("\r\n", rtrim($csv, "\r\n"));
}

it('downloads the orders per day of the picked range as dated rows', function () {
    $response = $this->get('/lathe/api/dashboards/main/widgets/orders-per-day/csv?range=7')
        ->assertOk()
        ->assertDownload('orders-per-day-'.now()->toDateString().'.csv');

    expect($response->headers->get('Content-Type'))->toStartWith('text/csv');

    $lines = csvLines((string) $response->getContent());
    $rows = array_map(fn (string $line) => explode(',', $line), array_slice($lines, 1));
    $placed = Order::where('placed_at', '>=', now()->subDays(6)->startOfDay())->where('placed_at', '<', now()->addDay()->startOfDay())->count();

    expect($placed)->toBeGreaterThan(0)
        ->and($lines[0])->toBe('date,orders')
        ->and(array_column($rows, 0))->toBe(array_map(fn (int $ago) => now()->subDays($ago)->toDateString(), [6, 5, 4, 3, 2, 1, 0]))
        ->and(array_sum(array_map(intval(...), array_column($rows, 1))))->toBe($placed);
});

it('writes plain numbers and quotes labels a spreadsheet would misread', function () {
    Cards::$widgets = [new BigBars];
    Lathe::registerDashboards(Cards::class);

    $csv = (string) $this->get('/lathe/api/dashboards/cards/widgets/big-bars/csv')->assertOk()->getContent();

    expect(csvLines($csv))->toBe([
        'label,value',
        '"Paid, online",1948466.99',
        '"\'=HYPERLINK(""x"")",1581',
        'Refunded,-12',
    ]);
});

it('offers a CSV for a chart card and not for a value card', function () {
    $this->get('/lathe/api/dashboards/main/widgets/orders/csv')->assertNotFound();

    $widgets = collect($this->getJson('/lathe/api/dashboards/main/widgets')->json('widgets'))->keyBy('uriKey');

    expect($widgets['orders']['csv'])->toBeFalse()
        ->and($widgets['orders-per-day']['csv'])->toBeTrue();
});

it('answers 403 for a hidden dashboard and 404 for an unknown widget on the CSV route', function () {
    $this->get('/lathe/api/dashboards/main/widgets/nope/csv')->assertNotFound();

    Lathe::dashboards()->flush();
    Lathe::registerDashboards(Cards::make()->canSee(fn () => false));

    $this->get('/lathe/api/dashboards/cards/widgets/order-count/csv')->assertForbidden();
});
