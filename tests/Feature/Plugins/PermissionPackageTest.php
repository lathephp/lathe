<?php

use Illuminate\Foundation\PackageManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Lathe\Permission\PermissionPlugin;
use Lathe\Plugins\PluginDiscovery;
use Spatie\Permission\Traits\HasRoles;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

it('installs lathe/permission from plugins/ so discovery finds its plugin', function () {
    $vendor = PluginDiscovery::vendorPath(app(PackageManifest::class)->vendorPath);

    expect((new PluginDiscovery($vendor))->classes())->toContain(PermissionPlugin::class);
});

it('migrates the Spatie tables and gives the workbench User its roles', function () {
    expect(Schema::hasTable('roles'))->toBeTrue()
        ->and(Schema::hasTable('model_has_roles'))->toBeTrue()
        ->and(class_uses_recursive(User::class))->toContain(HasRoles::class);
});
