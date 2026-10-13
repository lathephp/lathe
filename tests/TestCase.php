<?php

namespace Lathe\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;
use Lathe\Resources\ResourceRegistry;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    /**
     * Discover vendor packages (Inertia, Fortify) the way a host app does.
     *
     * @var bool
     */
    protected $enablesPackageDiscoveries = true;

    /**
     * The database this process created on a server lane, so it is created once per process.
     */
    protected static ?string $serverDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Parallel test processes share the skeleton's bootstrap folder, so each gets its own manifest cache.
        $this->app->make(ResourceRegistry::class)->useCachePath(
            sys_get_temp_dir().'/lathe-resources-'.getmypid().'.php',
        );

        $this->startOnEmptyServerDatabase();
    }

    /**
     * Tests expect the empty database a fresh SQLite :memory: connection gives them.
     * On MySQL, MariaDB or PostgreSQL each parallel process gets its own database,
     * and every test starts with its tables dropped.
     */
    protected function startOnEmptyServerDatabase(): void
    {
        $connection = (string) config('database.default');

        if (config("database.connections.{$connection}.driver") === 'sqlite') {
            return;
        }

        $token = ParallelTesting::token();

        if ($token !== false) {
            $database = config("database.connections.{$connection}.database").'_'.$token;

            if (static::$serverDatabase !== $database) {
                Schema::dropDatabaseIfExists($database);
                Schema::createDatabase($database);
                static::$serverDatabase = $database;
            }

            config()->set("database.connections.{$connection}.database", $database);
            DB::purge($connection);
        }

        Schema::dropAllTables();
    }
}
