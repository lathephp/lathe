<?php

use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\PartitionWidget;
use Lathe\Widgets\Results\PartitionResult;
use Workbench\App\Models\Customer;

/**
 * A partition card whose value is whatever the callback returns; the callback runs inside the
 * card so it can reach the protected group-by helpers.
 */
function partitionCard(Closure $calculate): PartitionWidget
{
    return new class($calculate) extends PartitionWidget
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

it('sends asBar and showLabels in the descriptor', function () {
    $card = partitionCard(fn () => null)->asBar()->showLabels();

    expect($card->descriptor())->toMatchArray(['component' => 'partition-widget', 'asBar' => true, 'showLabels' => true]);
});

it('sends each segment with its share of the total and its colour', function () {
    $card = partitionCard(fn () => (new PartitionResult(['a' => 1, 'b' => 3, 'c' => 4], ['c' => '#00ff00']))
        ->label(fn (string $label) => strtoupper($label)));

    expect($card->resolve(LatheRequest::create('/')))->toBe(['value' => [
        ['label' => 'A', 'value' => 1, 'percentage' => 13.0, 'color' => 'var(--chart-1)'],
        ['label' => 'B', 'value' => 3, 'percentage' => 38.0, 'color' => 'var(--chart-2)'],
        ['label' => 'C', 'value' => 4, 'percentage' => 50.0, 'color' => '#00ff00'],
    ]]);
});

it('sends shares to the set precision', function () {
    $card = partitionCard(fn () => (new PartitionResult(['a' => 1, 'b' => 2]))->precision(1));

    expect(array_column($card->resolve(LatheRequest::create('/'))['value'], 'percentage'))->toBe([33.3, 66.7]);
});

it('sends an empty list for a chart with no data', function () {
    expect(partitionCard(fn () => new PartitionResult([]))->resolve(LatheRequest::create('/')))->toBe(['value' => []]);
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

    $segments = partitionCard(fn (LatheRequest $request) => $this->{$helper}($request, Customer::class, ...$arguments))
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
