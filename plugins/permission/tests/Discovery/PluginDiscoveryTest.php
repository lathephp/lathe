<?php

use Lathe\Permission\PermissionPlugin;
use Lathe\Permission\PermissionServiceProvider;
use Lathe\Permission\Tests\Discovery\InstalledJson;
use Lathe\Plugins\PluginDiscovery;

/**
 * The install path is `composer require` and nothing else. These run Lathe's real parser over
 * the real manifest, so a misspelt key, a wrong namespace or a class that stops extending
 * Plugin fails here.
 */
it('is discovered from its own composer manifest', function () {
    $vendor = InstalledJson::write();

    try {
        expect((new PluginDiscovery($vendor))->classes())->toContain(PermissionPlugin::class);
    } finally {
        InstalledJson::forget($vendor);
    }
});

it('is discovered when listed among other installed packages', function () {
    $vendor = InstalledJson::write([
        ['name' => 'vendor/no-extra'],
        ['name' => 'vendor/other-plugin', 'extra' => ['lathe' => ['plugin' => 'Vendor\\Missing\\Plugin']]],
    ]);

    try {
        expect((new PluginDiscovery($vendor))->classes())->toBe([PermissionPlugin::class]);
    } finally {
        InstalledJson::forget($vendor);
    }
});

// The provider registers the cache listeners, so extra.lathe.plugin is added beside it, not instead.
it('still declares its Laravel service provider for package discovery', function () {
    expect(data_get(InstalledJson::packageManifest(), 'extra.laravel.providers'))
        ->toContain(PermissionServiceProvider::class);
});
