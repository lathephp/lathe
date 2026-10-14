<?php

use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\PieWidget;
use Lathe\Widgets\Results\PieResult;
use Workbench\App\Models\Customer;

/**
 * A pie card whose value is whatever the callback returns; the callback runs inside the card
 * so it can reach the protected group-by helpers.
 */
function pieCard(Closure $calculate): PieWidget
{
    return new class($calculate) extends PieWidget
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

it('sends the card options in the descriptor', function () {
    $card = pieCard(fn () => null)->separatorNone()->showTotal()->activeDonut()->showCustomLabel();

    expect($card->descriptor())->toMatchArray([
        'component' => 'pie-widget',
        'height' => 'double',
        'separatorNone' => true,
        'showTotal' => true,
        'activeDonut' => ['strokeWidth' => 5, 'activeIndex' => 0],
        'labelMode' => 'custom',
    ]);
});

it('keeps only the last label mode asked for', function () {
    expect(pieCard(fn () => null)->showInlineLabel()->showLabelList()->descriptor()['labelMode'])->toBe('list')
        ->and(pieCard(fn () => null)->showLabelList()->showInlineLabel()->descriptor()['labelMode'])->toBe('inline');
});

it('sends a full pie for innerRadius(0) and a donut for innerRadius(0.6)', function () {
    $request = LatheRequest::create('/');

    expect(pieCard(fn () => (new PieResult(['a' => 1]))->innerRadius(0))->resolve($request)['innerRadius'])->toBe(0.0)
        ->and(pieCard(fn () => (new PieResult(['a' => 1]))->innerRadius(0.6))->resolve($request)['innerRadius'])->toBe(0.6);
});

it('sends the segments, labels and series of the result', function () {
    $card = pieCard(fn () => (new PieResult(['a' => 1, 'b' => 3], ['b' => '#00ff00']))
        ->label(fn (string $label) => strtoupper($label))
        ->showLabels()
        ->withSeries(['2025' => ['a' => 1, 'b' => 1], '2026' => ['a' => 3]]));

    expect($card->resolve(LatheRequest::create('/')))->toBe([
        'value' => [
            ['label' => 'A', 'value' => 1, 'percentage' => 25.0, 'color' => 'var(--chart-1)'],
            ['label' => 'B', 'value' => 3, 'percentage' => 75.0, 'color' => '#00ff00'],
        ],
        'innerRadius' => 0.0,
        'showLabels' => true,
        'series' => [
            ['name' => '2025', 'segments' => [
                ['label' => 'A', 'value' => 1, 'percentage' => 50.0, 'color' => 'var(--chart-1)'],
                ['label' => 'B', 'value' => 1, 'percentage' => 50.0, 'color' => '#00ff00'],
            ]],
            ['name' => '2026', 'segments' => [
                ['label' => 'A', 'value' => 3, 'percentage' => 100.0, 'color' => 'var(--chart-1)'],
            ]],
        ],
    ]);
});

it('sends an empty list for a chart with no data', function () {
    expect(pieCard(fn () => new PieResult([]))->resolve(LatheRequest::create('/'))['value'])->toBe([]);
});

it('groups by a column through the helpers and sends at most 20 segments', function (string $helper, array $arguments, string $lastLabel, int|float $lastValue) {
    $this->artisan('migrate')->assertSuccessful();

    $rows = [];

    for ($i = 1; $i <= 25; $i++) {
        for ($row = 1; $row <= $i; $row++) {
            $rows[] = ['name' => sprintf('n%02d', $i), 'email' => sprintf('n%02d-%d@example.com', $i, $row), 'credit' => $i];
        }
    }

    DB::table('customers')->insert($rows);

    $segments = pieCard(fn (LatheRequest $request) => $this->{$helper}($request, Customer::class, ...$arguments))
        ->resolve(LatheRequest::create('/'))['value'];

    expect($segments)->toHaveCount(20)
        ->and($segments[0]['label'])->toBe('n25')
        ->and($segments[19])->toMatchArray(['label' => $lastLabel, 'value' => $lastValue]);
})->with([
    'count' => ['count', ['name'], 'Other', 21],
    'sum' => ['sum', ['credit', 'name'], 'Other', 91.0],
    'average' => ['average', ['credit', 'name'], 'n06', 6.0],
    'max' => ['max', ['credit', 'name'], 'n06', 6.0],
    'min' => ['min', ['credit', 'name'], 'n06', 6.0],
]);
