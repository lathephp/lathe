<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lathe\Lathe;
use Lathe\Query\DateBucket;
use Lathe\Support\Timezone;

it('uses the user timezone when the callback names a real zone', function () {
    Lathe::userTimezone(fn (Request $request) => 'Pacific/Kiritimati');

    expect(Lathe::resolveUserTimezone(Request::create('/')))->toBe('Pacific/Kiritimati')
        ->and(app(Timezone::class)->forRequest(Request::create('/')))->toBe('Pacific/Kiritimati');
});

it('falls back to the app timezone for no callback, null or a made-up zone', function (?Closure $callback) {
    config(['app.timezone' => 'Europe/Paris']);

    if ($callback !== null) {
        Lathe::userTimezone($callback);
    }

    expect(app(Timezone::class)->forRequest(Request::create('/')))->toBe('Europe/Paris');
})->with([
    'no callback' => [null],
    'null' => [fn () => fn () => null],
    'a made-up zone' => [fn () => fn () => 'Mars/Olympus'],
]);

it('puts a UTC moment on the local calendar day', function (string $zone, string $day) {
    $placed = CarbonImmutable::parse('2026-03-08 23:30:00', 'UTC');

    expect(app(Timezone::class)->localDay($placed, $zone))->toBe($day);
})->with([
    ['UTC', '2026-03-08'],
    ['Pacific/Kiritimati', '2026-03-09'],
    ['America/New_York', '2026-03-08'],
]);

it('gives the UTC bounds of a local day, a short one on the spring-forward day', function () {
    [$start, $end] = app(Timezone::class)->dayBounds('2026-03-08', 'America/New_York');

    expect($start->toIso8601ZuluString())->toBe('2026-03-08T05:00:00Z')
        ->and($end->toIso8601ZuluString())->toBe('2026-03-09T04:00:00Z');

    [$start, $end] = app(Timezone::class)->dayBounds('2026-03-09', 'Pacific/Kiritimati');

    expect($start->toIso8601ZuluString())->toBe('2026-03-08T10:00:00Z')
        ->and($end->toIso8601ZuluString())->toBe('2026-03-09T10:00:00Z');
});

it('buckets dates by day and by month in SQL', function () {
    Schema::create('bucketed', function (Blueprint $table) {
        $table->id();
        $table->date('day');
    });
    DB::table('bucketed')->insert([['day' => '2026-03-08'], ['day' => '2026-03-08'], ['day' => '2026-03-09'], ['day' => '2026-04-01']]);

    $days = DB::table('bucketed')->selectRaw(DateBucket::day(DB::connection(), 'bucketed.day').' as bucket, count(*) as total')
        ->groupByRaw(DateBucket::day(DB::connection(), 'bucketed.day'))->orderBy('bucket')->get()
        ->mapWithKeys(fn ($row) => [substr((string) $row->bucket, 0, 10) => (int) $row->total])->all();
    $months = DB::table('bucketed')->selectRaw(DateBucket::month(DB::connection(), 'bucketed.day').' as bucket, count(*) as total')
        ->groupByRaw(DateBucket::month(DB::connection(), 'bucketed.day'))->orderBy('bucket')->get()
        ->mapWithKeys(fn ($row) => [substr((string) $row->bucket, 0, 10) => (int) $row->total])->all();

    expect($days)->toBe(['2026-03-08' => 2, '2026-03-09' => 1, '2026-04-01' => 1])
        ->and($months)->toBe(['2026-03-01' => 3, '2026-04-01' => 1]);
});
