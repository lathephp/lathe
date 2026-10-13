<?php

namespace Lathe\Tests;

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

    protected function setUp(): void
    {
        parent::setUp();

        // Parallel test processes share the skeleton's bootstrap folder, so each gets its own manifest cache.
        $this->app->make(ResourceRegistry::class)->useCachePath(
            sys_get_temp_dir().'/lathe-resources-'.getmypid().'.php',
        );
    }
}
