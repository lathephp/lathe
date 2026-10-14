<?php

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Artisan;
use Lathe\Assets\AssetManifest;
use Lathe\Auth\FortifyConfig;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Lathe;
use Lathe\Pages\PageRegistry;
use Lathe\Plugins\PluginRegistry;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\ConfigStore;

it('answers the panel name, paths and version', function () {
    config(['lathe.name' => 'Acme Admin']);

    expect(Lathe::name())->toBe('Acme Admin')
        ->and(Lathe::path())->toBe('/lathe')
        ->and(Lathe::loginPath())->toBe('/lathe/login')
        ->and(Lathe::version())->toBe(InstalledVersions::getPrettyVersion('lathe/lathe') ?? 'dev');
});

it('lists the Lathe version in about', function () {
    Artisan::call('about', ['--only' => 'lathe']);

    expect(Artisan::output())->toContain('Lathe')->toContain(Lathe::version());
});

it('resolves each accessor to one binding', function (string $accessor, string $class) {
    expect(Lathe::{$accessor}())->toBeInstanceOf($class)
        ->and(Lathe::{$accessor}())->toBe(app($class));
})->with([
    ['config', ConfigStore::class],
    ['resources', ResourceRegistry::class],
    ['plugins', PluginRegistry::class],
    ['dashboards', DashboardRegistry::class],
    ['pages', PageRegistry::class],
    ['assets', AssetManifest::class],
    ['fortify', FortifyConfig::class],
]);
