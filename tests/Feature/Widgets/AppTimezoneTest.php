<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lathe\Support\Timezone;
use Lathe\Widgets\AggregateQuery;
use Workbench\App\Models\Order;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    DB::table('customers')->insert(['name' => 'Ada', 'email' => 'ada@example.com', 'country' => 'GB']);
});

/**
 * Insert one order whose placed_at holds the text, as the app's forms store it.
 */
function zonedOrder(string $placedAt): void
{
    static $number = 0;

    DB::table('orders')->insert([
        'customer_id' => 1,
        'number' => sprintf('TZ-%08d', ++$number),
        'status' => 'paid',
        'total_cents' => 100,
        'placed_at' => $placedAt,
        'created_at' => $placedAt,
        'updated_at' => $placedAt,
    ]);
}

function placedQuery(string $zone): AggregateQuery
{
    return AggregateQuery::for(Order::class)->dateColumn('placed_at')->timezone($zone);
}

it('counts an order on the app day it was stored for a user in the same zone', function () {
    appTimezone('Asia/Dhaka');
    zonedOrder('2026-11-11 23:30:00');

    $this->travelTo(CarbonImmutable::parse('2026-11-11 23:45:00', 'Asia/Dhaka'));
    $november11 = placedQuery('Asia/Dhaka')->byDays(1)->calendarValue();

    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'Asia/Dhaka'));
    $november12 = placedQuery('Asia/Dhaka')->byDays(1)->calendarValue();
    [$start, $end] = app(Timezone::class)->dayBounds('2026-11-11', 'Asia/Dhaka');

    expect($november11->value)->toBe(1)
        ->and($november12->value)->toBe(0)
        ->and($november12->previous)->toBe(1)
        ->and(placedQuery('Asia/Dhaka')->valueBetween($start, $end->subSecond())->value)->toBe(1)
        ->and(placedQuery('Asia/Dhaka')->byDays(2)->trend()->jsonSerialize())->toBe(['2026-11-11' => 1, '2026-11-12' => 0]);
});

it('puts an order stored in the app zone on the day of the user zone', function (string $app, string $placed, string $user, array $days) {
    appTimezone($app);
    zonedOrder($placed);
    $this->travelTo(CarbonImmutable::parse('2026-11-12 06:00:00', 'UTC'));

    expect(placedQuery($user)->byDays(2)->trend()->jsonSerialize())->toBe($days);
})->with([
    'Dhaka app, UTC user' => ['Asia/Dhaka', '2026-11-11 23:30:00', 'UTC', ['2026-11-11' => 1, '2026-11-12' => 0]],
    'Dhaka app, Kiritimati user' => ['Asia/Dhaka', '2026-11-11 23:30:00', 'Pacific/Kiritimati', ['2026-11-11' => 0, '2026-11-12' => 1]],
    'New York app, New York user' => ['America/New_York', '2026-11-11 23:30:00', 'America/New_York', ['2026-11-11' => 1, '2026-11-12' => 0]],
    'New York app, UTC user' => ['America/New_York', '2026-11-11 23:30:00', 'UTC', ['2026-11-11' => 0, '2026-11-12' => 1]],
]);

it('keeps the 23 hour day whole on a New York app', function () {
    appTimezone('America/New_York');
    zonedOrder('2026-03-08 00:30:00');
    zonedOrder('2026-03-08 23:30:00');
    zonedOrder('2026-03-09 00:30:00');
    $this->travelTo(CarbonImmutable::parse('2026-03-10 12:00:00', 'America/New_York'));

    expect(placedQuery('America/New_York')->byDays(3)->trend()->jsonSerialize())
        ->toBe(['2026-03-08' => 2, '2026-03-09' => 1, '2026-03-10' => 0]);
});

it('moves an order across the spring change by the app offset of its own moment', function () {
    appTimezone('America/New_York');
    zonedOrder('2026-03-07 18:30:00'); // 23:30 UTC, EST
    zonedOrder('2026-03-08 20:30:00'); // 00:30 UTC on the 9th, EDT
    $this->travelTo(CarbonImmutable::parse('2026-03-10 12:00:00', 'UTC'));

    expect(placedQuery('UTC')->byDays(3)->trend()->jsonSerialize())
        ->toBe(['2026-03-08' => 0, '2026-03-09' => 1, '2026-03-10' => 0])
        ->and(placedQuery('UTC')->byDays(4)->trend()->jsonSerialize()['2026-03-07'])->toBe(1);
});

it('labels an hour bucket at the user clock time when the app zone is another', function () {
    appTimezone('Asia/Kolkata');
    zonedOrder('2026-11-11 23:30:00'); // 18:00 UTC, 13:00 in New York
    $this->travelTo(CarbonImmutable::parse('2026-11-11 14:30:00', 'America/New_York'));

    $trend = placedQuery('America/New_York')->byHours(3)->trend()->jsonSerialize();

    expect($trend)->toBe(['2026-11-11T12:00:00-05:00' => 0, '2026-11-11T13:00:00-05:00' => 1, '2026-11-11T14:00:00-05:00' => 0]);
});

it('counts the rolling range against the app wall time', function () {
    appTimezone('Asia/Dhaka');
    zonedOrder('2026-11-12 11:30:00'); // 05:30 UTC
    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'Asia/Dhaka'));

    expect(placedQuery('UTC')->byHours(1)->value()->value)->toBe(1);
});
