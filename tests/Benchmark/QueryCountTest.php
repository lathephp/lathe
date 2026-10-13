<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Tests\Benchmark\ResultFile;
use Lathe\Tests\TestCase;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

uses(TestCase::class)->group('benchmark');

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    // 10 is not a default page size; the comparison needs it.
    config(['lathe.pagination.per_page_options' => [10, 25, 50, 100]]);
});

/**
 * @return array{queries: int, bytes: int}
 */
function listCost(int $perPage): array
{
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $body = (string) test()->getJson("/lathe/api/resources/orders?per_page={$perPage}")->assertOk()->getContent();

    return ['queries' => $queries, 'bytes' => strlen($body)];
}

it('runs the same number of queries at 10 and 100 rows and records the payload sizes', function () {
    Model::preventLazyLoading();
    $driver = DB::connection()->getDriverName();

    $ten = listCost(10);
    DB::flushQueryLog();
    $hundred = listCost(100);
    $twentyFive = listCost(25);

    expect($hundred['queries'])->toBe($ten['queries']);

    ResultFile::append([
        ['name' => "{$driver} list queries", 'value' => $ten['queries'], 'unit' => 'queries', 'budget' => null],
        ['name' => "{$driver} list bytes 25 rows", 'value' => $twentyFive['bytes'], 'unit' => 'bytes', 'budget' => null],
        ['name' => "{$driver} list bytes 100 rows", 'value' => $hundred['bytes'], 'unit' => 'bytes', 'budget' => null],
    ]);
});
