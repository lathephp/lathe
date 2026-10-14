<?php

use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\RadarWidget;
use Lathe\Widgets\Results\RadarResult;
use Workbench\App\Models\Customer;

/**
 * A radar card whose value is whatever the callback returns; the callback runs inside the card
 * so it can reach the protected group-by helpers.
 */
function radarCard(Closure $calculate): RadarWidget
{
    return new class($calculate) extends RadarWidget
    {
        public function __construct(private Closure $calculate)
        {
            parent::__construct();
        }

        public function calculate(LatheRequest $request): mixed
        {
            return $this->calculate->call($this, $request);
        }
    };
}

$axes = [['key' => 'mon', 'label' => 'Mon'], ['key' => 'tue', 'label' => 'Tue']];
$datasets = [['name' => 'Orders', 'data' => [3, 5]]];

it('sends a double height radar card', function () {
    expect(radarCard(fn () => null)->descriptor())->toMatchArray(['component' => 'radar-widget', 'height' => 'double']);
});

it('sends the defaults of a plain radar result', function () use ($axes, $datasets) {
    expect(radarCard(fn () => new RadarResult($axes, $datasets))->resolve(LatheRequest::create('/')))->toBe([
        'axes' => $axes,
        'datasets' => $datasets,
        'dots' => false,
        'gridType' => 'polygon',
        'fillOpacity' => 0.6,
        'lineOnly' => false,
        'gridNone' => false,
        'radialLines' => true,
        'gridFilled' => false,
        'showLegend' => false,
    ]);
});

it('sends every option set on the result', function () use ($axes, $datasets) {
    $card = radarCard(fn () => (new RadarResult($axes, $datasets))
        ->dots()->gridType('circle')->fillOpacity(0.2)->lineOnly()->gridNone()
        ->radialLines(false)->gridFilled()->showLegend()->polarRadius([10, 20, 30]));

    expect($card->resolve(LatheRequest::create('/')))->toBe([
        'axes' => $axes,
        'datasets' => $datasets,
        'dots' => true,
        'gridType' => 'circle',
        'fillOpacity' => 0.2,
        'lineOnly' => true,
        'gridNone' => true,
        'radialLines' => false,
        'gridFilled' => true,
        'showLegend' => true,
        'polarRadius' => [10, 20, 30],
    ]);
});

it('sends no axes and no datasets for a chart with no data', function () {
    $sent = radarCard(fn () => new RadarResult)->resolve(LatheRequest::create('/'));

    expect($sent['axes'])->toBe([])->and($sent['datasets'])->toBe([]);
});

it('groups by a column through the helpers into at most 20 axes', function (string $helper, array $arguments, string $lastKey, int|float $lastValue) {
    $this->artisan('migrate')->assertSuccessful();

    $rows = [];

    for ($i = 1; $i <= 25; $i++) {
        for ($row = 1; $row <= $i; $row++) {
            $rows[] = ['name' => sprintf('n%02d', $i), 'email' => sprintf('n%02d-%d@example.com', $i, $row), 'credit' => $i];
        }
    }

    DB::table('customers')->insert($rows);

    $sent = radarCard(fn (LatheRequest $request) => $this->{$helper}($request, Customer::class, ...$arguments))
        ->resolve(LatheRequest::create('/'));

    expect($sent['axes'])->toHaveCount(20)
        ->and($sent['axes'][0])->toBe(['key' => 'n25', 'label' => 'n25'])
        ->and($sent['axes'][19])->toBe(['key' => $lastKey, 'label' => $lastKey])
        ->and($sent['datasets'])->toHaveCount(1)
        ->and($sent['datasets'][0]['name'])->toBe('Value')
        ->and($sent['datasets'][0]['data'])->toHaveCount(20)
        ->and($sent['datasets'][0]['data'][19])->toBe($lastValue);
})->with([
    'count' => ['count', ['name'], 'Other', 21],
    'sum' => ['sum', ['credit', 'name'], 'Other', 91.0],
    'average' => ['average', ['credit', 'name'], 'n06', 6.0],
    'max' => ['max', ['credit', 'name'], 'n06', 6.0],
    'min' => ['min', ['credit', 'name'], 'n06', 6.0],
]);
