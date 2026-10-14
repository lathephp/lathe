<?php

use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\BarWidget;
use Lathe\Widgets\Results\BarResult;
use Workbench\App\Models\Customer;

/**
 * A bar card whose value is whatever the callback returns; the callback runs inside the card
 * so it can reach the protected group-by helpers.
 */
function barCard(Closure $calculate): BarWidget
{
    return new class($calculate) extends BarWidget
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

function barGroups(): void
{
    $rows = [];

    for ($i = 1; $i <= 25; $i++) {
        for ($row = 1; $row <= $i; $row++) {
            $rows[] = ['name' => sprintf('n%02d', $i), 'email' => sprintf('n%02d-%d@example.com', $i, $row), 'credit' => $i];
        }
    }

    DB::table('customers')->insert($rows);
}

it('sends the card options in the descriptor', function () {
    $card = barCard(fn () => null)->showLabels()->insideLabels()->activeIndex(0);

    expect($card->descriptor())->toMatchArray(['component' => 'bar-widget', 'showLabels' => true, 'insideLabels' => true, 'activeIndex' => 0]);
});

it('sends the result options in the value', function () {
    $card = barCard(fn () => (new BarResult(['a' => 1, 'b' => -2.5], ['b' => '#0000ff']))
        ->horizontal()
        ->stacked()
        ->withSeries(['2025' => ['a' => 1, 'b' => 2], '2026' => ['a' => 3, 'b' => 4]])
        ->negativeColor('#ff0000')
        ->label(fn (string $label) => strtoupper($label))
        ->transform(fn (int|float $value) => $value * 10));

    expect($card->resolve(LatheRequest::create('/')))->toBe([
        'bars' => [
            ['label' => 'A', 'value' => 10, 'color' => 'var(--chart-1)'],
            ['label' => 'B', 'value' => -25.0, 'color' => '#0000ff'],
        ],
        'horizontal' => true,
        'stacked' => true,
        'series' => [
            '2025' => [['label' => 'a', 'value' => 1], ['label' => 'b', 'value' => 2]],
            '2026' => [['label' => 'a', 'value' => 3], ['label' => 'b', 'value' => 4]],
        ],
        'negativeColor' => '#ff0000',
    ]);
});

it('sends an empty bar list for a chart with no data', function () {
    expect(barCard(fn () => new BarResult([]))->resolve(LatheRequest::create('/')))->toBe(['bars' => []]);
});

it('colours the bars from the palette and keeps the plain data map for the result', function () {
    $result = new BarResult(['a' => 1, 'b' => 2]);

    expect($result->jsonSerialize())->toBe(['a' => 1, 'b' => 2])
        ->and(array_column($result->payload()['bars'], 'color'))->toBe(['var(--chart-1)', 'var(--chart-2)']);
});

it('groups by a column through the helpers and sends at most 20 bars', function (string $helper, array $arguments, int|float $first, string $lastLabel, int|float $lastValue) {
    $this->artisan('migrate')->assertSuccessful();
    barGroups();

    $card = barCard(fn (LatheRequest $request) => $this->{$helper}($request, Customer::class, ...$arguments));
    $bars = $card->resolve(LatheRequest::create('/'))['bars'];

    expect($bars)->toHaveCount(20)
        ->and($bars[0])->toMatchArray(['label' => 'n25', 'value' => $first])
        ->and($bars[19])->toMatchArray(['label' => $lastLabel, 'value' => $lastValue]);
})->with([
    'count' => ['count', ['name'], 25, 'Other', 21],
    'sum' => ['sum', ['credit', 'name'], 625.0, 'Other', 91.0],
    'average' => ['average', ['credit', 'name'], 25.0, 'n06', 6.0],
    'max' => ['max', ['credit', 'name'], 25.0, 'n06', 6.0],
    'min' => ['min', ['credit', 'name'], 25.0, 'n06', 6.0],
]);
