<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Widgets\TrendProbe;
use Lathe\Widgets\Results\TrendResult;
use Lathe\Widgets\Widget;
use Lathe\Widgets\WidgetPresenter;
use Workbench\App\Models\Order;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    DB::table('customers')->insert(['name' => 'Ada', 'email' => 'ada@example.com', 'country' => 'GB']);
    TrendProbe::$using = null;
});

/**
 * The `value` field of the widget's response for a request with this query.
 *
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function trendOf(Widget $widget, array $query = []): array
{
    $value = (new WidgetPresenter($widget))->toResponse(LatheRequest::create('/', 'GET', $query))['value'];

    return is_array($value) ? $value : [];
}

function trendOrder(string $createdAt, int $totalCents = 100): void
{
    static $number = 0;

    DB::table('orders')->insert([
        'customer_id' => 1,
        'number' => sprintf('TRD-%08d', ++$number),
        'status' => 'paid',
        'total_cents' => $totalCents,
        'placed_at' => $createdAt,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

/**
 * A probe widget that returns this result.
 */
function trendProbe(TrendResult $result): TrendProbe
{
    TrendProbe::$using = fn () => $result;

    return new TrendProbe;
}

it('sends the trend and its latest value for the days helper', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    trendOrder('2026-05-15 01:00:00');
    trendOrder('2026-05-15 02:00:00');
    trendOrder('2026-05-14 02:00:00');
    TrendProbe::$using = fn (LatheRequest $request) => $this->countByDays($request, Order::class);

    $payload = trendOf(new TrendProbe, ['range' => 3]);

    expect($payload['trend'])->toBe(['2026-05-13' => 0, '2026-05-14' => 1, '2026-05-15' => 2])
        ->and($payload['value'])->toBe(2);
});

it('takes the first range key when the request names none', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    TrendProbe::$using = fn (LatheRequest $request) => $this->countByDays($request, Order::class);

    expect(trendOf(new TrendProbe)['trend'])->toHaveCount(30)
        ->and(trendOf(new TrendProbe, ['range' => 90])['trend'])->toHaveCount(90)
        ->and(trendOf(new TrendProbe, ['range' => 'forever'])['trend'])->toHaveCount(30);
});

it('buckets by the unit of each helper', function (string $helper, array $args, array $expected) {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    trendOrder('2026-05-15 10:05:00', 100);
    trendOrder('2026-05-15 10:10:00', 300);
    trendOrder('2026-05-14 10:10:00', 50);
    TrendProbe::$using = fn (LatheRequest $request) => $this->{$helper}($request, Order::class, ...$args);

    expect(trendOf(new TrendProbe, ['range' => 2])['trend'])->toBe($expected);
})->with([
    'count by days' => ['countByDays', [], ['2026-05-14' => 1, '2026-05-15' => 2]],
    'count by months' => ['countByMonths', [], ['2026-04' => 0, '2026-05' => 3]],
    'count by weeks' => ['countByWeeks', [], ['2026-05-04' => 0, '2026-05-11' => 3]],
    'count by hours' => ['countByHours', [], ['2026-05-15T09:00:00+00:00' => 0, '2026-05-15T10:00:00+00:00' => 2]],
    'count by minutes' => ['countByMinutes', [], ['2026-05-15T10:19:00+00:00' => 0, '2026-05-15T10:20:00+00:00' => 0]],
    'sum by days' => ['sumByDays', ['total_cents'], ['2026-05-14' => 50.0, '2026-05-15' => 400.0]],
    'average by days' => ['averageByDays', ['total_cents'], ['2026-05-14' => 50.0, '2026-05-15' => 200.0]],
    'min by days' => ['minByDays', ['total_cents'], ['2026-05-14' => 50.0, '2026-05-15' => 100.0]],
    'max by days' => ['maxByDays', ['total_cents'], ['2026-05-14' => 50.0, '2026-05-15' => 300.0]],
]);

it('dates the helpers by the column given', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    trendOrder('2020-01-01 10:00:00');
    DB::table('orders')->update(['placed_at' => '2026-05-15 08:00:00']);
    TrendProbe::$using = fn (LatheRequest $request) => $this->countByDays($request, Order::class, null, 'placed_at');

    expect(trendOf(new TrendProbe, ['range' => 1])['trend'])->toBe(['2026-05-15' => 1]);
});

it('offers 7 days, 30 days and 12 months by default', function () {
    expect((new TrendProbe)->ranges())->toBe([7 => '7 days', 30 => '30 days', 365 => '12 months'])
        ->and((new TrendProbe)->component())->toBe('trend-widget');
});

it('puts the display options in the descriptor with the value set', function () {
    $widget = (new TrendProbe)
        ->lineOnly()->stacked()->stackedExpand()->curveType('step')->showAxes()->showDots()
        ->dotStyle('custom')->dotColors()->showLabels()->labelKey('status')->twelveHourTime()->defaultRange(60);

    expect($widget->descriptor())->toMatchArray([
        'lineOnly' => true,
        'stacked' => true,
        'stackedExpand' => true,
        'curveType' => 'step',
        'showAxes' => true,
        'showDots' => true,
        'dotStyle' => 'custom',
        'dotColors' => true,
        'showLabels' => true,
        'labelKey' => 'status',
        'twelveHourTime' => true,
        'defaultRange' => 60,
    ]);
});

it('describes a plain trend widget with every option off', function () {
    expect((new TrendProbe)->descriptor())->toMatchArray([
        'lineOnly' => false,
        'stacked' => false,
        'stackedExpand' => false,
        'curveType' => null,
        'showAxes' => false,
        'showDots' => false,
        'dotStyle' => null,
        'dotColors' => false,
        'showLabels' => false,
        'labelKey' => null,
        'twelveHourTime' => false,
        'defaultRange' => 30,
    ]);
});

it('refuses a curve type it does not know', function (string $curve) {
    expect(fn () => (new TrendProbe)->curveType($curve))->toThrow(LogicException::class);
})->with(['spline', '']);

it('takes each curve type it knows', function (string $curve) {
    expect((new TrendProbe)->curveType($curve)->descriptor()['curveType'])->toBe($curve);
})->with(['monotone', 'linear', 'step', 'natural']);

it('takes the default range from defaultRange() when the request names none', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    TrendProbe::$using = fn (LatheRequest $request) => $this->countByDays($request, Order::class);

    expect(trendOf((new TrendProbe)->defaultRange(60))['trend'])->toHaveCount(60);
});

it('sends the headline as the latest value unless result() sets one', function () {
    expect(trendOf(trendProbe(new TrendResult(['a' => 1, 'b' => 4])))['value'])->toBe(4)
        ->and(trendOf(trendProbe((new TrendResult(['a' => 1, 'b' => 4]))->result(10)))['value'])->toBe(10)
        ->and(trendOf(trendProbe(new TrendResult([])))['value'])->toBeNull();
});

it('shows the latest or the sum as the headline', function () {
    expect(trendOf(trendProbe((new TrendResult(['a' => 1, 'b' => 4, 'c' => 2]))->showSumValue()))['value'])->toBe(7)
        ->and(trendOf(trendProbe((new TrendResult(['a' => 1, 'b' => 4, 'c' => 2]))->showSumValue()->showLatestValue()))['value'])->toBe(2)
        ->and((new TrendResult(['a' => 1, 'b' => 4]))->latest())->toBe(4)
        ->and((new TrendResult([]))->latest())->toBeNull();
});

it('replaces the points with trend()', function () {
    $payload = trendOf(trendProbe((new TrendResult(['a' => 1]))->trend(['x' => 5, 'y' => 6])));

    expect($payload['trend'])->toBe(['x' => 5, 'y' => 6]);
});

it('sends series and labels', function () {
    $series = ['paid' => ['a' => 1, 'b' => 2], 'open' => ['a' => 3, 'b' => 4]];
    $result = (new TrendResult(['a' => 4, 'b' => 6]))->withSeries($series)->labels(['a' => 'Mon', 'b' => 'Tue']);

    expect(trendOf(trendProbe($result)))->toMatchArray(['series' => $series, 'labels' => ['a' => 'Mon', 'b' => 'Tue']])
        ->and(trendOf(trendProbe(new TrendResult(['a' => 1]))))->toMatchArray(['series' => null, 'labels' => null]);
});

it('sends prefix, suffix, format and transforms the headline on the server', function () {
    $result = (new TrendResult(['a' => 250]))->dollars()->suffix('order')->withoutSuffixInflection()->format(['style' => 'percent'])
        ->transform(fn (int $cents) => $cents / 100);

    expect(trendOf(trendProbe($result)))->toMatchArray([
        'prefix' => '$',
        'suffix' => 'order',
        'suffixInflection' => false,
        'format' => ['style' => 'percent'],
        'value' => 2.5,
        'trend' => ['a' => 2.5],
    ])->and(trendOf(trendProbe((new TrendResult([]))->euros()))['prefix'])->toBe('€');
});

it('transforms the points and the series as well as the headline', function () {
    $result = (new TrendResult(['a' => 150000, 'b' => 250]))->withSeries(['paid' => ['a' => 100000, 'b' => 250]])
        ->transform(fn (int $cents) => $cents / 100);

    expect(trendOf(trendProbe($result)))->toMatchArray([
        'trend' => ['a' => 1500, 'b' => 2.5],
        'series' => ['paid' => ['a' => 1000, 'b' => 2.5]],
        'value' => 2.5,
    ]);
});

it('rounds the headline and the points with the widget precision and says which', function () {
    $widget = trendProbe(new TrendResult(['a' => 2.5, 'b' => 3.14159]));
    $widget->precision(2);

    expect(trendOf($widget))->toMatchArray([
        'trend' => ['a' => 2.5, 'b' => 3.14],
        'value' => 3.14,
        'precision' => 2,
        'roundingMode' => PHP_ROUND_HALF_UP,
    ]);

    $even = trendProbe(new TrendResult(['a' => 2.5]));
    $even->precision(0, PHP_ROUND_HALF_EVEN);

    expect(trendOf($even)['value'])->toBe(2);
});

it('pins the flat points of a TrendResult nobody dressed up', function () {
    expect((new TrendResult(['a' => 1, 'b' => 2]))->jsonSerialize())->toBe(['a' => 1, 'b' => 2]);
});
