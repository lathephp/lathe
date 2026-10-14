<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Lathe\Tests\Fixtures\Widgets\ValueProbe;
use Lathe\Widgets\Results\ValueResult;
use Lathe\Widgets\ValueWidget;
use Lathe\Widgets\Widget;
use Lathe\Widgets\WidgetPresenter;
use Workbench\App\Models\Order;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    DB::table('customers')->insert(['name' => 'Ada', 'email' => 'ada@example.com', 'country' => 'GB']);
    ValueProbe::$using = null;
});

/**
 * The `value` field of the widget's response for a request with this query.
 *
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function valueOf(Widget $widget, array $query = []): array
{
    $value = (new WidgetPresenter($widget))->toResponse(LatheRequest::create('/', 'GET', $query))['value'];

    return is_array($value) ? $value : [];
}

function valueOrder(string $createdAt, int $totalCents = 100): void
{
    static $number = 0;

    DB::table('orders')->insert([
        'customer_id' => 1,
        'number' => sprintf('VAL-%08d', ++$number),
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
function valueProbe(ValueResult $result): ValueProbe
{
    ValueProbe::$using = fn () => $result;

    return new ValueProbe;
}

it('sends the value, the previous value and the change for range 30', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    valueOrder('2026-05-14 10:00:00');
    valueOrder('2026-05-01 10:00:00');
    valueOrder('2026-04-10 10:00:00');
    valueOrder('2026-03-01 10:00:00');

    $payload = valueOf(new ValueOrders, ['range' => 30]);

    expect($payload)->toMatchArray(['value' => 2, 'previous' => 1, 'change' => 100.0]);
});

it('takes the first range key when the request names none', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    valueOrder('2026-05-14 10:00:00');
    valueOrder('2026-04-01 10:00:00');

    expect(valueOf(new ValueOrders)['value'])->toBe(1)
        ->and(valueOf(new ValueOrders, ['range' => 60])['value'])->toBe(2);
});

it('sends null for no value and 0 as a zero that is not allowed until asked for', function () {
    expect(valueOf(valueProbe(new ValueResult(null))))->toMatchArray(['value' => null, 'allowZeroResult' => false])
        ->and(valueOf(valueProbe(new ValueResult(0))))->toMatchArray(['value' => 0, 'allowZeroResult' => false])
        ->and(valueOf(valueProbe((new ValueResult(0))->allowZeroResult())))->toMatchArray(['value' => 0, 'allowZeroResult' => true]);
});

it('sends the suffix and whether the client inflects it', function () {
    expect(valueOf(valueProbe((new ValueResult(2))->suffix('order'))))->toMatchArray(['suffix' => 'order', 'suffixInflection' => true])
        ->and(valueOf(valueProbe((new ValueResult(2))->suffix('sales'))))->toMatchArray(['suffix' => 'sales', 'suffixInflection' => true])
        ->and(valueOf(valueProbe((new ValueResult(2))->suffix('order')->withoutSuffixInflection())))->toMatchArray(['suffix' => 'order', 'suffixInflection' => false]);
});

it('prefixes the currency symbol', function (Closure $set, string $prefix) {
    expect(valueOf(valueProbe($set(new ValueResult(5))))['prefix'])->toBe($prefix);
})->with([
    'dollars' => [fn (ValueResult $r) => $r->dollars(), '$'],
    'euros' => [fn (ValueResult $r) => $r->euros(), '€'],
    'currency' => [fn (ValueResult $r) => $r->currency('£'), '£'],
    'currency default' => [fn (ValueResult $r) => $r->currency(), '$'],
]);

it('runs transform on the value on the server', function () {
    $payload = valueOf(valueProbe((new ValueResult(250))->transform(fn (int $cents) => $cents / 100)));

    expect($payload['value'])->toBe(2.5);
});

it('runs transform on the previous value too, so both share a unit', function () {
    $payload = valueOf(valueProbe((new ValueResult(192074700, 184977227))->transform(fn (int $cents) => round($cents / 100))));

    expect($payload['value'])->toBe(1920747.0)
        ->and($payload['previous'])->toBe(1849772.0)
        ->and($payload['change'])->toBe(3.84);
});

it('sends the format as given', function (string|array $format) {
    expect(valueOf(valueProbe((new ValueResult(5))->format($format)))['format'])->toBe($format);
})->with([
    'compact' => ['a'],
    'fraction digits' => ['.2'],
    'intl options' => [['style' => 'percent']],
]);

it('sends the tooltip format and the copyable flag', function () {
    expect(valueOf(valueProbe((new ValueResult(5))->tooltipFormat('.2')->copyable())))->toMatchArray(['tooltipFormat' => '.2', 'copyable' => true])
        ->and(valueOf(valueProbe(new ValueResult(5))))->toMatchArray(['tooltipFormat' => null, 'copyable' => false]);
});

it('rounds on the server with the widget precision and says which', function (int $precision, int $mode, float $value, int|float $expected) {
    $widget = valueProbe(new ValueResult($value));
    $widget->precision($precision, $mode);

    expect(valueOf($widget))->toMatchArray(['value' => $expected, 'precision' => $precision, 'roundingMode' => $mode]);
})->with([
    'half up' => [0, PHP_ROUND_HALF_UP, 2.5, 3],
    'half even' => [0, PHP_ROUND_HALF_EVEN, 2.5, 2],
    'two places' => [2, PHP_ROUND_HALF_UP, 3.14159, 3.14],
]);

it('sends no rounding when the widget set none', function () {
    expect(valueOf(valueProbe(new ValueResult(2.5))))->toMatchArray(['value' => 2.5, 'precision' => null, 'roundingMode' => null]);
});

it('sends the change next to the value', function (int|float $current, int|float|null $previous, ?float $change) {
    expect(valueOf(valueProbe(new ValueResult($current, $previous)))['change'])->toBe($change);
})->with([
    'up half' => [150, 100, 50.0],
    'rounds to two places' => [7, 3, 133.33],
    'previous zero' => [5, 0, null],
    'previous null' => [5, null, null],
]);

it('sends the previous value with its label', function () {
    expect(valueOf(valueProbe((new ValueResult(150))->previous(100, 'last month'))))
        ->toMatchArray(['previous' => 100, 'previousLabel' => 'last month', 'change' => 50.0]);
});

it('offers 30, 60 and 365 days by default', function () {
    expect((new ValueOrders)->ranges())->toBe([30 => '30 Days', 60 => '60 Days', 365 => '365 Days'])
        ->and((new ValueOrders)->descriptor()['ranges'])->toBe([
            ['value' => 30, 'label' => '30 Days'],
            ['value' => 60, 'label' => '60 Days'],
            ['value' => 365, 'label' => '365 Days'],
        ])
        ->and((new ValueOrders)->component())->toBe('value-widget');
});

it('compares each range constant with the period before it', function (string $range, array $inside, array $outside) {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));

    foreach ([...$inside['current'], ...$inside['previous'], ...$outside] as $moment) {
        valueOrder($moment);
    }

    expect(valueOf(new ValueOrders, ['range' => $range]))
        ->toMatchArray(['value' => count($inside['current']), 'previous' => count($inside['previous'])]);
})->with([
    'today' => [ValueWidget::TODAY, ['current' => ['2026-05-15 00:00:00', '2026-05-15 09:00:00'], 'previous' => ['2026-05-14 23:59:00']], ['2026-05-13 23:59:00']],
    'yesterday' => [ValueWidget::YESTERDAY, ['current' => ['2026-05-14 00:00:00', '2026-05-14 23:59:00'], 'previous' => ['2026-05-13 12:00:00']], ['2026-05-15 01:00:00', '2026-05-12 12:00:00']],
    'this week' => [ValueWidget::THIS_WEEK, ['current' => ['2026-05-11 00:00:00', '2026-05-15 01:00:00'], 'previous' => ['2026-05-10 23:00:00', '2026-05-04 00:00:00']], ['2026-05-03 12:00:00']],
    'month to date' => [ValueWidget::MTD, ['current' => ['2026-05-01 00:00:00', '2026-05-12 01:00:00'], 'previous' => ['2026-04-30 23:00:00', '2026-04-01 00:00:00']], ['2026-03-31 12:00:00']],
    'quarter to date' => [ValueWidget::QTD, ['current' => ['2026-04-01 00:00:00', '2026-05-12 01:00:00'], 'previous' => ['2026-03-31 23:00:00', '2026-01-01 00:00:00']], ['2025-12-31 12:00:00']],
    'year to date' => [ValueWidget::YTD, ['current' => ['2026-01-01 00:00:00', '2026-05-12 01:00:00'], 'previous' => ['2025-12-31 23:00:00', '2025-01-01 00:00:00']], ['2024-12-31 12:00:00']],
]);

it('counts every row for ALL and has no previous value', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    valueOrder('2026-05-14 10:00:00');
    valueOrder('2020-01-01 10:00:00');

    expect(valueOf(new ValueOrders, ['range' => ValueWidget::ALL]))->toMatchArray(['value' => 2, 'previous' => null, 'change' => null]);
});

it('falls back to the default range for a range it does not know', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    valueOrder('2026-05-14 10:00:00');
    valueOrder('2026-04-01 10:00:00');

    expect(valueOf(new ValueOrders, ['range' => 'forever'])['value'])->toBe(1)
        ->and(valueOf(new ValueOrders, ['range' => 5000])['value'])->toBe(1);
});

it('sums, averages and takes the minimum and maximum of a column over the range', function (string $function, int|float $expected) {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    valueOrder('2026-05-14 10:00:00', 100);
    valueOrder('2026-05-13 10:00:00', 300);
    ValueProbe::$using = fn (LatheRequest $request) => $this->{$function}($request, Order::class, 'total_cents');

    expect(valueOf(new ValueProbe)['value'])->toBe($expected);
})->with([
    'sum' => ['sum', 400.0],
    'average' => ['average', 200.0],
    'min' => ['min', 100.0],
    'max' => ['max', 300.0],
]);

it('dates the helpers by the column given', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    valueOrder('2020-01-01 10:00:00');
    DB::table('orders')->update(['placed_at' => '2026-05-14 10:00:00']);
    ValueProbe::$using = fn (LatheRequest $request) => $this->count($request, Order::class, null, 'placed_at');

    expect(valueOf(new ValueProbe)['value'])->toBe(1);
});

it('runs exactly 2 queries to count a range', function () {
    valueOrder('2026-05-14 10:00:00');

    DB::enableQueryLog();
    valueOf(new ValueOrders, ['range' => 30]);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(2);
});

it('puts the icon in the descriptor', function () {
    expect((new ValueOrders)->descriptor()['icon'])->toBeNull()
        ->and((new ValueOrders)->icon(Icon::ShoppingCart)->descriptor()['icon'])->toBe('shopping-cart');
});

it('takes the default range when the request names none', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    valueOrder('2026-05-14 10:00:00');
    valueOrder('2026-04-01 10:00:00');

    $widget = (new ValueOrders)->defaultRange(60);

    expect(valueOf($widget)['value'])->toBe(2)
        ->and(valueOf($widget, ['range' => 30])['value'])->toBe(1)
        ->and($widget->descriptor()['defaultRange'])->toBe(60)
        ->and((new ValueOrders)->descriptor()['defaultRange'])->toBeNull();
});
