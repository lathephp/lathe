<?php

namespace Lathe\Permission\Tests\Discovery;

use Lathe\Lathe;
use Lathe\Permission\Tests\TestCase as BaseTestCase;
use Lathe\Plugins\PluginDiscovery;

/**
 * Registers the plugin the way a host app gets it from `composer require` alone: Lathe's
 * PluginDiscovery reads extra.lathe.plugin from installed.json. Nothing here registers it by hand.
 */
abstract class TestCase extends BaseTestCase
{
    protected string $vendor;

    protected function registerPluginUnderTest(): void
    {
        $this->vendor = InstalledJson::write();

        (new PluginDiscovery($this->vendor))->register(Lathe::plugins());
    }

    protected function tearDown(): void
    {
        InstalledJson::forget($this->vendor);

        parent::tearDown();
    }
}
