<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lathe\Lathe;
use Lathe\Widgets\AggregateQuery;
use Lathe\Widgets\Results\ValueResult;
use Lathe\Widgets\TrendUnit;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;

class AggregateThing extends Model
{
    protected $table = 'aggregate_things';

    protected $guarded = [];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    DB::table('customers')->insert(['name' => 'Ada', 'email' => 'ada@example.com', 'country' => 'GB']);
});

/**
 * Insert one order created at a UTC moment.
 */
function aggregateOrder(string $createdAt, int $totalCents = 100, string $status = 'paid'): void
{
    static $number = 0;

    DB::table('orders')->insert([
        'customer_id' => 1,
        'number' => sprintf('AGG-%08d', ++$number),
        'status' => $status,
        'total_cents' => $totalCents,
        'placed_at' => $createdAt,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

/**
 * Customers n01 to n25, group n{i} holding i rows of credit i.
 */
function aggregateGroups(): void
{
    $rows = [];

    for ($i = 1; $i <= 25; $i++) {
        for ($row = 1; $row <= $i; $row++) {
            $rows[] = ['name' => sprintf('n%02d', $i), 'email' => sprintf('n%02d-%d@example.com', $i, $row), 'credit' => $i];
        }
    }

    DB::table('customers')->insert($rows);
}

it('puts an order in the day bucket of the user zone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-02 02:00:00', 'UTC'));
    aggregateOrder('2026-06-01 10:30:00');

    $trend = AggregateQuery::for(Order::class)->timezone('Pacific/Kiritimati')->byDays(2)->trend();

    expect($trend->jsonSerialize())->toBe(['2026-06-01' => 0, '2026-06-02' => 1]);
});

it('keeps both ends of the 23 hour day in one bucket', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-10 12:00:00', 'UTC'));
    aggregateOrder('2026-03-08 06:30:00');
    aggregateOrder('2026-03-09 03:30:00');

    $trend = AggregateQuery::for(Order::class)->timezone('America/New_York')->byDays(3)->trend();

    expect($trend->jsonSerialize())->toBe(['2026-03-08' => 2, '2026-03-09' => 0, '2026-03-10' => 0]);
});

it('gives the 25 hour day 25 hourly buckets', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 05:00:00', 'UTC'));
    aggregateOrder('2026-11-01 05:30:00');
    aggregateOrder('2026-11-01 06:30:00');

    $trend = AggregateQuery::for(Order::class)->timezone('America/New_York')->byHours(26)->trend()->jsonSerialize();
    $november1 = array_filter(array_keys($trend), fn (string $key) => str_starts_with($key, '2026-11-01'));

    expect($trend)->toHaveCount(26)
        ->and($november1)->toHaveCount(25)
        ->and($trend['2026-11-01T01:00:00-04:00'])->toBe(1)
        ->and($trend['2026-11-01T01:00:00-05:00'])->toBe(1);
});

it('returns the same buckets for each unit, with missing buckets as 0', function (TrendUnit $unit, array $moments, array $expected) {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    array_map(aggregateOrder(...), $moments);

    $trend = AggregateQuery::for(Order::class)->timezone('UTC')->by($unit, 3)->trend();

    expect($trend->jsonSerialize())->toBe($expected);
})->with([
    'minute' => [TrendUnit::Minute, ['2026-05-15 10:20:10', '2026-05-15 10:20:50', '2026-05-15 10:18:59', '2026-05-15 10:10:00'],
        ['2026-05-15T10:18:00+00:00' => 1, '2026-05-15T10:19:00+00:00' => 0, '2026-05-15T10:20:00+00:00' => 2]],
    'hour' => [TrendUnit::Hour, ['2026-05-15 10:05:00', '2026-05-15 10:50:00', '2026-05-15 08:30:00', '2026-05-15 07:59:00'],
        ['2026-05-15T08:00:00+00:00' => 1, '2026-05-15T09:00:00+00:00' => 0, '2026-05-15T10:00:00+00:00' => 2]],
    'day' => [TrendUnit::Day, ['2026-05-15 00:00:00', '2026-05-15 09:00:00', '2026-05-13 23:59:59', '2026-05-12 23:59:59'],
        ['2026-05-13' => 1, '2026-05-14' => 0, '2026-05-15' => 2]],
    'week' => [TrendUnit::Week, ['2026-05-11 00:00:00', '2026-05-14 12:00:00', '2026-05-03 12:00:00', '2026-04-26 12:00:00'],
        ['2026-04-27' => 1, '2026-05-04' => 0, '2026-05-11' => 2]],
    'month' => [TrendUnit::Month, ['2026-05-01 00:00:00', '2026-05-15 08:00:00', '2026-03-31 23:59:00', '2026-02-28 12:00:00'],
        ['2026-03' => 1, '2026-04' => 0, '2026-05' => 2]],
    'quarter' => [TrendUnit::Quarter, ['2026-04-01 00:00:00', '2026-05-02 12:00:00', '2025-12-31 12:00:00', '2025-09-30 12:00:00'],
        ['2025-Q4' => 1, '2026-Q1' => 0, '2026-Q2' => 2]],
    'year' => [TrendUnit::Year, ['2026-01-01 00:00:00', '2026-05-01 12:00:00', '2024-12-31 23:59:00', '2023-12-31 12:00:00'],
        ['2024' => 1, '2025' => 0, '2026' => 2]],
]);

it('sums, averages and takes the minimum and maximum per bucket', function (string $function, int|float $expected) {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    aggregateOrder('2026-05-15 01:00:00', 100);
    aggregateOrder('2026-05-15 02:00:00', 300);
    aggregateOrder('2026-05-14 02:00:00', 50);

    $trend = AggregateQuery::for(Order::class)->timezone('UTC')->{$function}('total_cents')->byDays(2)->trend();

    expect($trend->jsonSerialize()['2026-05-15'])->toBe($expected);
})->with([
    'sum' => ['sum', 400.0],
    'average' => ['average', 200.0],
    'min' => ['min', 100.0],
    'max' => ['max', 300.0],
]);

it('allows 366 trend points and refuses 367', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));

    expect(AggregateQuery::for(Order::class)->timezone('UTC')->byDays(366)->trend()->jsonSerialize())->toHaveCount(366)
        ->and(fn () => AggregateQuery::for(Order::class)->byDays(367))->toThrow(LogicException::class);
});

it('refuses bar and partition without groupBy', function (string $terminal) {
    expect(fn () => AggregateQuery::for(Order::class)->{$terminal}())->toThrow(LogicException::class);
})->with(['bar', 'partition']);

it('orders groups by aggregate descending and labels a null key N/A', function (string $terminal) {
    DB::table('customers')->insert([
        ['name' => 'B', 'email' => 'b@example.com', 'country' => null],
        ['name' => 'C', 'email' => 'c@example.com', 'country' => null],
        ['name' => 'D', 'email' => 'd@example.com', 'country' => 'FR'],
        ['name' => 'E', 'email' => 'e@example.com', 'country' => 'FR'],
        ['name' => 'F', 'email' => 'f@example.com', 'country' => 'FR'],
    ]);

    $result = AggregateQuery::for(Customer::class)->groupBy('country')->{$terminal}();

    expect($result->jsonSerialize())->toBe(['FR' => 3, 'N/A' => 2, 'GB' => 1]);
})->with(['bar', 'partition']);

it('caps groups at 20, merging the rest into Other for count and sum', function (string $function, array $arguments, array $top, int|float $other) {
    aggregateGroups();
    DB::table('customers')->where('id', 1)->delete();

    $query = AggregateQuery::for(Customer::class)->{$function}(...$arguments)->groupBy('name');
    $bar = $query->bar()->jsonSerialize();

    expect($bar)->toHaveCount(20)
        ->and(array_slice($bar, 0, 3, true))->toBe($top)
        ->and(array_key_last($bar))->toBe('Other')
        ->and($bar['Other'])->toBe($other)
        ->and($query->partition()->jsonSerialize())->toBe($bar);
})->with([
    'count' => ['count', [], ['n25' => 25, 'n24' => 24, 'n23' => 23], 21],
    'sum' => ['sum', ['credit'], ['n25' => 625.0, 'n24' => 576.0, 'n23' => 529.0], 91.0],
]);

it('drops the remainder past 20 groups for average, min and max', function (string $function) {
    aggregateGroups();
    DB::table('customers')->where('id', 1)->delete();

    $bar = AggregateQuery::for(Customer::class)->{$function}('credit')->groupBy('name')->bar()->jsonSerialize();

    expect($bar)->toHaveCount(20)
        ->and(array_key_first($bar))->toBe('n25')
        ->and(array_key_last($bar))->toBe('n06')
        ->and($bar['n25'])->toBe(25.0)
        ->and($bar)->not->toHaveKey('Other');
})->with(['average', 'min', 'max']);

it('aggregate_columns_use_grammar_wrapping', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    aggregateOrder('2026-05-15 01:00:00');
    DB::table('customers')->where('id', 1)->update(['created_at' => '2026-05-15 01:00:00']);

    $joined = AggregateQuery::for(Order::class)->timezone('UTC')->byDays(1)
        ->query(fn (Builder $query) => $query->join('customers', 'customers.id', '=', 'orders.customer_id'))
        ->trend()->jsonSerialize();

    DB::connection()->setTablePrefix('lx_');
    Schema::create('aggregate_things', function (Blueprint $table) {
        $table->id();
        $table->string('order');
        $table->timestamps();
    });
    DB::table('aggregate_things')->insert([
        ['order' => 'first', 'created_at' => '2026-05-15 01:00:00'],
        ['order' => 'first', 'created_at' => '2026-05-15 02:00:00'],
        ['order' => 'second', 'created_at' => '2026-05-15 03:00:00'],
    ]);

    expect($joined)->toBe(['2026-05-15' => 1])
        ->and(AggregateQuery::for(AggregateThing::class)->timezone('UTC')->byDays(1)->trend()->jsonSerialize())->toBe(['2026-05-15' => 3])
        ->and(AggregateQuery::for(AggregateThing::class)->groupBy('order')->bar()->jsonSerialize())->toBe(['first' => 2, 'second' => 1]);
})->group('regression');

it('computes ValueResult change as a rounded percentage', function (mixed $current, mixed $previous, ?float $change) {
    expect((new ValueResult($current, $previous))->change())->toBe($change);
})->with([
    'up half' => [150, 100, 50.0],
    'rounds to two places' => [7, 3, 133.33],
    'previous zero' => [5, 0, null],
    'previous null' => [5, null, null],
]);

it('compares the last range with the one before it', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-15 10:20:30', 'UTC'));
    aggregateOrder('2026-05-14 10:00:00');
    aggregateOrder('2026-05-01 10:00:00');
    aggregateOrder('2026-04-10 10:00:00');
    aggregateOrder('2026-04-09 10:00:00');
    aggregateOrder('2026-03-01 10:00:00');

    $result = AggregateQuery::for(Order::class)->timezone('UTC')->byDays(30)->value();

    expect($result->value)->toBe(2)
        ->and($result->previous)->toBe(2)
        ->and($result->change())->toBe(0.0);
});

it('takes explicit bounds for valueBetween', function () {
    aggregateOrder('2026-05-02 10:00:00', 100);
    aggregateOrder('2026-05-03 10:00:00', 200);
    aggregateOrder('2026-04-02 10:00:00', 100);

    $result = AggregateQuery::for(Order::class)->sum('total_cents')->valueBetween(
        CarbonImmutable::parse('2026-05-01', 'UTC'), CarbonImmutable::parse('2026-05-31', 'UTC'),
        CarbonImmutable::parse('2026-04-01', 'UTC'), CarbonImmutable::parse('2026-04-30', 'UTC'),
    );

    expect($result->value)->toBe(300.0)
        ->and($result->previous)->toBe(100.0)
        ->and($result->change())->toBe(200.0);
});

it('uses the panel user zone unless timezone() overrides it', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-02 02:00:00', 'UTC'));
    aggregateOrder('2026-06-01 10:30:00');
    Lathe::userTimezone(fn (Request $request) => 'Pacific/Kiritimati');

    $query = AggregateQuery::for(Order::class)->byDays(2);

    expect($query->trend()->jsonSerialize())->toBe(['2026-06-01' => 0, '2026-06-02' => 1])
        ->and($query->timezone('UTC')->trend()->jsonSerialize())->toBe(['2026-06-01' => 1, '2026-06-02' => 0]);
});

it('names the tables it reads', function () {
    $query = AggregateQuery::for(Order::class)
        ->query(fn (Builder $query) => $query->join('customers', 'customers.id', '=', 'orders.customer_id'));

    expect($query->tables())->toBe(['orders', 'customers']);
});
