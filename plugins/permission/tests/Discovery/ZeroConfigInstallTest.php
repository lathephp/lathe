<?php

use Lathe\Lathe;
use Lathe\Permission\PermissionPlugin;
use Lathe\Permission\Resources\PermissionResource;
use Lathe\Permission\Resources\RoleResource;
use Spatie\Permission\Models\Role;

/**
 * What a host app gets from a bare `composer require`: the plugin arrives through
 * PluginDiscovery only, and nothing here calls Lathe::registerPlugins() first.
 */
it('registers the plugin without a Lathe::registerPlugins() call', function () {
    expect(Lathe::plugins()->find('permission'))->toBeInstanceOf(PermissionPlugin::class);
});

it('registers the Role and Permission resources', function () {
    expect(Lathe::resources()->forKey('roles'))->toBe(RoleResource::class)
        ->and(Lathe::resources()->forKey('permissions'))->toBe(PermissionResource::class);
});

it('denies the roles endpoint until the app opts in', function () {
    $this->signInAdmin();

    $this->getJson('/lathe/api/resources/roles')->assertForbidden();
});

it('serves the roles endpoint once the discovered plugin is opted in', function () {
    $this->signInAdmin();
    Role::create(['name' => 'editor']);

    // A discovered plugin has registered already, so its opt-in applies the policies again.
    Lathe::plugins()->find('permission')?->withDefaultPolicies()->register();

    $this->getJson('/lathe/api/resources/roles')->assertOk();
});

// An app that registered the plugin by hand before this suite existed keeps working beside discovery.
it('does not double-register when the app also registers the plugin by hand', function () {
    $discovered = Lathe::plugins()->find('permission');

    Lathe::registerPlugins(PermissionPlugin::make());

    expect(Lathe::plugins()->all())->toHaveCount(1)
        ->and(Lathe::plugins()->find('permission'))->toBe($discovered)
        ->and(Lathe::resources()->forKey('roles'))->toBe(RoleResource::class);
});
