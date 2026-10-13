<?php

namespace Lathe\Tests\Benchmark;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Lathe\Tests\TestCase;

/**
 * Boots the workbench on the seeded database LATHE_BENCH_DATABASE names, as it is.
 */
abstract class BenchmarkTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (static::benchDatabase() !== null && DB::connection()->getDriverName() === 'sqlite') {
            $this->fail('LATHE_BENCH_DATABASE is set but the connection is SQLite. Load the lane first: eval "$(bash scripts/lanes.sh env mysql)".');
        }
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $database = static::benchDatabase();

        if ($database !== null) {
            $app['config']->set('database.connections.'.$app['config']->string('database.default').'.database', $database);
        }
    }

    /**
     * The timing runs measure a seeded snapshot, so nothing drops its tables.
     */
    protected function startOnEmptyServerDatabase(): void {}

    public static function benchDatabase(): ?string
    {
        $database = getenv('LATHE_BENCH_DATABASE');

        return is_string($database) && $database !== '' ? $database : null;
    }
}
