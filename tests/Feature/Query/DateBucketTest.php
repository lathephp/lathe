<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lathe\Query\DateBucket;
use Lathe\Widgets\TrendUnit;

/**
 * The bucket SQL of 1.0.0-beta.12 on a UTC app, over a range that crosses New York's spring change.
 */
const UTC_APP_BUCKETS = [
    'sqlite' => [
        'Minute' => 'strftime(\'%Y-%m-%d %H:%M:00\', datetime("orders"."placed_at", ((CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)) || \' seconds\'))',
        'Hour' => 'strftime(\'%Y-%m-%d %H:00\', datetime("orders"."placed_at", ((CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)) || \' seconds\'))',
        'Day' => 'strftime(\'%Y-%m-%d\', datetime("orders"."placed_at", ((CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)) || \' seconds\'))',
        'Week' => 'strftime(\'%Y-%m-%d\', datetime("orders"."placed_at", ((CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)) || \' seconds\'), \'-\' || ((CAST(strftime(\'%w\', datetime("orders"."placed_at", ((CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)) || \' seconds\')) AS INTEGER) + 6) % 7) || \' days\')',
        'Month' => 'strftime(\'%Y-%m\', datetime("orders"."placed_at", ((CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)) || \' seconds\'))',
        'Quarter' => 'strftime(\'%Y\', datetime("orders"."placed_at", ((CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)) || \' seconds\')) || \'-Q\' || ((CAST(strftime(\'%m\', datetime("orders"."placed_at", ((CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)) || \' seconds\')) AS INTEGER) + 2) / 3)',
        'Year' => 'strftime(\'%Y\', datetime("orders"."placed_at", ((CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)) || \' seconds\'))',
        'offset' => '(CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)',
        'fixed' => 'strftime(\'%Y-%m-%d\', datetime("orders"."placed_at", (21600) || \' seconds\'))',
    ],
    'mysql' => [
        'Minute' => 'date_format(date_add("orders"."placed_at", interval (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) second), \'%Y-%m-%d %H:%i:00\')',
        'Hour' => 'date_format(date_add("orders"."placed_at", interval (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) second), \'%Y-%m-%d %H:00\')',
        'Day' => 'date_format(date_add("orders"."placed_at", interval (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) second), \'%Y-%m-%d\')',
        'Week' => 'date_format(date_sub(date_add("orders"."placed_at", interval (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) second), interval weekday(date_add("orders"."placed_at", interval (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) second)) day), \'%Y-%m-%d\')',
        'Month' => 'date_format(date_add("orders"."placed_at", interval (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) second), \'%Y-%m\')',
        'Quarter' => 'concat(year(date_add("orders"."placed_at", interval (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) second)), \'-Q\', quarter(date_add("orders"."placed_at", interval (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) second)))',
        'Year' => 'date_format(date_add("orders"."placed_at", interval (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) second), \'%Y\')',
        'offset' => '(CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)',
        'fixed' => 'date_format(date_add("orders"."placed_at", interval 21600 second), \'%Y-%m-%d\')',
    ],
    'pgsql' => [
        'Minute' => 'to_char(date_trunc(\'minute\', ("orders"."placed_at" + (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) * interval \'1 second\')), \'YYYY-MM-DD HH24:MI:SS\')',
        'Hour' => 'to_char(date_trunc(\'hour\', ("orders"."placed_at" + (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) * interval \'1 second\')), \'YYYY-MM-DD HH24:MI\')',
        'Day' => 'to_char(("orders"."placed_at" + (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) * interval \'1 second\'), \'YYYY-MM-DD\')',
        'Week' => 'to_char(date_trunc(\'week\', ("orders"."placed_at" + (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) * interval \'1 second\')), \'YYYY-MM-DD\')',
        'Month' => 'to_char(("orders"."placed_at" + (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) * interval \'1 second\'), \'YYYY-MM\')',
        'Quarter' => 'to_char(("orders"."placed_at" + (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) * interval \'1 second\'), \'YYYY"-Q"Q\')',
        'Year' => 'to_char(("orders"."placed_at" + (CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END) * interval \'1 second\'), \'YYYY\')',
        'offset' => '(CASE WHEN "orders"."placed_at" >= \'2026-03-08 07:00:00\' THEN -14400 ELSE -18000 END)',
        'fixed' => 'to_char(("orders"."placed_at" + 21600 * interval \'1 second\'), \'YYYY-MM-DD\')',
    ],
];

it('writes the bucket SQL of beta.12 on a UTC app', function (string $driver) {
    // A SQLite connection's grammar quotes as the pinned text does, on every lane.
    config(['database.connections.bucket_pin' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    $connection = Mockery::mock(DB::connection('bucket_pin'))->makePartial();
    $connection->shouldReceive('getDriverName')->andReturn($driver);
    $from = CarbonImmutable::parse('2026-03-01 00:00:00', 'America/New_York');
    $to = CarbonImmutable::parse('2026-03-15 00:00:00', 'America/New_York');
    $written = [];

    foreach (TrendUnit::cases() as $unit) {
        $written[$unit->name] = DateBucket::zoned($connection, 'orders.placed_at', $unit, 'America/New_York', $from, $to, 'UTC');
    }

    $written['offset'] = DateBucket::offset($connection, 'orders.placed_at', 'America/New_York', $from, $to, 'UTC');
    $written['fixed'] = DateBucket::zoned($connection, 'orders.placed_at', TrendUnit::Day, 'Asia/Dhaka', $from, $to, 'UTC');

    expect($written)->toBe(UTC_APP_BUCKETS[$driver]);
})->with(['sqlite', 'mysql', 'pgsql']);
