<?php

/*
 * Helpers for the browser tests of queued runs: a file database a second process can read,
 * and a queue worker in that process.
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Workbench\Database\Seeders\DemoSeeder;

/**
 * Move this test onto a seeded file SQLite database with a database queue. The in-memory
 * database lives in this process only, and the transaction around each browser test hides
 * its rows from any other, so a queue worker could read neither.
 */
function onFileDatabase(): void
{
    // The wrapping transaction holds the seed. Rolling it back leaves the in-memory
    // database the next test reuses as migrated and empty.
    DB::rollBack();

    // A queued job names the connection of each model it carries, so the test and the
    // worker use one the worker can point at the file: sqlite, which reads DB_DATABASE.
    $testing = DB::getDefaultConnection();
    $file = database_path('lathe-browser-'.getmypid().'.sqlite');
    file_put_contents($file, '');
    config([
        'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true],
        'queue.default' => 'database',
        'queue.batching.database' => 'sqlite',
    ]);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    Artisan::call('migrate', ['--force' => true]);
    test()->seed(DemoSeeder::class);

    test()->beforeApplicationDestroyed(function () use ($testing, $file) {
        DB::purge('sqlite');
        DB::setDefaultConnection($testing);
        @unlink($file);
    });
}

/**
 * Run the database queue in a second process until it is empty, on the test's file database,
 * with the test's key and URL so the links it signs open in this server.
 */
function drainQueue(): void
{
    $process = new Process(
        [PHP_BINARY, 'vendor/bin/testbench', 'queue:work', 'database', '--stop-when-empty', '--sleep=0', '--tries=1'],
        dirname(__DIR__, 2),
        [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => DB::connection()->getDatabaseName(),
            'QUEUE_CONNECTION' => 'database',
            'APP_KEY' => config('app.key'),
            'APP_URL' => config('app.url'),
        ],
        timeout: 120,
    );

    $process->mustRun();
}
