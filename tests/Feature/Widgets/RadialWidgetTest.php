<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\RadialWidget;
use Lathe\Widgets\Results\RadialResult;
use Workbench\App\Models\Customer;

/**
 * A radial card whose value is whatever the callback returns; the callback runs inside the card
 * so it can reach the protected count and sum helpers.
 */
function radialCard(Closure $calculate): RadialWidget
{
    return new class($calculate) extends RadialWidget
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

function radialValue(RadialResult $result): array
{
    return radialCard(fn () => $result)->resolve(LatheRequest::create('/'));
}

it('sends a double height radial card', function () {
    expect(radialCard(fn () => null)->descriptor())->toMatchArray(['component' => 'radial-widget', 'height' => 'double']);
});

it('rounds the value and share to 2 decimals and sends the gauge defaults', function () {
    expect(radialValue(new RadialResult(66.666, 100)))->toBe([
        'value' => 66.67,
        'target' => 100,
        'percentage' => 66.67,
        'startAngle' => 0,
        'endAngle' => 360,
        'showTrack' => true,
        'showLabels' => false,
        'showGrid' => false,
        'color' => 'var(--success)',
    ]);
});

it('colours the gauge from the progress thresholds', function (int $value, string $color, string $avoided) {
    expect(radialValue(new RadialResult($value, 100))['color'])->toBe($color)
        ->and(radialValue((new RadialResult($value, 100))->avoid())['color'])->toBe($avoided);
})->with([
    '20' => [20, 'var(--destructive)', 'var(--success)'],
    '32' => [32, 'var(--destructive)', 'var(--success)'],
    '33' => [33, 'var(--warning)', 'var(--warning)'],
    '65' => [65, 'var(--warning)', 'var(--warning)'],
    '66' => [66, 'var(--success)', 'var(--destructive)'],
    '100' => [100, 'var(--success)', 'var(--destructive)'],
]);

it('adds "Lower is better" for avoid() only', function () {
    expect(radialValue((new RadialResult(50, 100))->avoid())['caption'])->toBe('Lower is better')
        ->and(radialValue(new RadialResult(50, 100)))->not->toHaveKey('caption');
});

it('lets color() override the threshold colour, avoided or not', function () {
    expect(radialValue((new RadialResult(10, 100))->color('#0000ff'))['color'])->toBe('#0000ff')
        ->and(radialValue((new RadialResult(10, 100))->avoid()->color('#0000ff'))['color'])->toBe('#0000ff');
});

it('sends the gauge options set on the result', function () {
    $sent = radialValue((new RadialResult(50, 200))->startAngle(-90)->endAngle(90)->showTrack(false)->label('Done')
        ->transform(fn (int|float $value) => $value * 2));

    expect($sent)->toMatchArray(['value' => 100, 'target' => 200, 'percentage' => 25.0, 'startAngle' => -90, 'endAngle' => 90, 'showTrack' => false, 'label' => 'Done']);
});

it('sends segments with their share and palette colour, and stacked items', function () {
    $sent = radialValue((new RadialResult(50, 100))
        ->segments([
            ['label' => 'A', 'value' => 30, 'target' => 60],
            ['label' => 'B', 'value' => 10, 'color' => '#00ff00'],
            ['label' => 'C', 'value' => 5],
        ])
        ->stacked([['name' => 'x', 'value' => 1], ['name' => 'y', 'value' => 2]])
        ->showLabels()
        ->showGrid());

    expect($sent['segments'])->toBe([
        ['label' => 'A', 'value' => 30, 'color' => 'var(--chart-1)', 'target' => 60, 'percentage' => 50.0],
        ['label' => 'B', 'value' => 10, 'color' => '#00ff00'],
        ['label' => 'C', 'value' => 5, 'color' => 'var(--chart-2)'],
    ])
        ->and($sent['stacked'])->toBe([['name' => 'x', 'value' => 1], ['name' => 'y', 'value' => 2]])
        ->and($sent)->toMatchArray(['showLabels' => true, 'showGrid' => true]);
});

it('counts the rows that pass the progress callable against all rows', function () {
    $this->artisan('migrate')->assertSuccessful();
    DB::table('customers')->insert([
        ['name' => 'a', 'email' => 'a@example.com', 'country' => 'GB', 'credit' => 10],
        ['name' => 'b', 'email' => 'b@example.com', 'country' => 'GB', 'credit' => 20],
        ['name' => 'c', 'email' => 'c@example.com', 'country' => 'GB', 'credit' => 30],
        ['name' => 'd', 'email' => 'd@example.com', 'country' => 'FR', 'credit' => 40],
    ]);
    $request = LatheRequest::create('/');
    $british = fn (Builder $query) => $query->where('country', 'GB');

    $count = radialCard(fn (LatheRequest $request) => $this->count($request, Customer::class, $british))->resolve($request);
    $sum = radialCard(fn (LatheRequest $request) => $this->sum($request, Customer::class, $british, 'credit'))->resolve($request);
    $targeted = radialCard(fn (LatheRequest $request) => $this->count($request, Customer::class, $british, null, 10))->resolve($request);

    expect($count)->toMatchArray(['value' => 3, 'target' => 4, 'percentage' => 75.0])
        ->and($sum)->toMatchArray(['value' => 60.0, 'target' => 100.0, 'percentage' => 60.0])
        ->and($targeted)->toMatchArray(['value' => 3, 'target' => 10, 'percentage' => 30.0]);
});
