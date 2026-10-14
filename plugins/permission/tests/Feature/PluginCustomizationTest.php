<?php

use Lathe\Exceptions\PluginDependencyException;
use Lathe\Exceptions\ResourceUriKeyCollisionException;
use Lathe\Lathe;
use Lathe\Permission\PermissionPlugin;
use Lathe\Permission\Policies\PermissionPolicy;
use Lathe\Permission\Policies\RolePolicy;
use Lathe\Permission\Resources\PermissionResource;
use Lathe\Permission\Resources\RoleResource;
use Lathe\Permission\Tests\Discovery\InstalledJson;
use Lathe\Permission\Tests\Fixtures\AppPermissionPolicy;
use Lathe\Permission\Tests\Fixtures\AppRolePolicy;
use Lathe\Permission\Tests\Fixtures\CustomRole;
use Lathe\Permission\Tests\Fixtures\HostRoleResource;
use Lathe\Permission\Tests\Fixtures\NonCanonicalRoleResource;
use Lathe\Permission\Tests\Fixtures\PluginWithoutSpatie;
use Lathe\Plugins\PluginDiscovery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('declares its name and uriKey', function () {
    expect(PermissionPlugin::make()->name())->toBe('Access Control')
        ->and(PermissionPlugin::uriKey())->toBe('permission');
});

it('declares spatie/laravel-permission as a hard requirement', function () {
    expect(PermissionPlugin::make()->requires())->toBe([PermissionRegistrar::class]);
});

it('names itself and the composer command when Spatie is missing', function () {
    expect(fn () => Lathe::registerPlugins(PluginWithoutSpatie::make()))->toThrow(
        PluginDependencyException::class,
        'Access Control ('.PluginWithoutSpatie::class.') needs spatie/laravel-permission. Run composer require spatie/laravel-permission.',
    );
});

it('contributes the Role and Permission resources', function () {
    expect(PermissionPlugin::make()->resources())->toBe([RoleResource::class, PermissionResource::class]);
});

it('maps the registrar model classes to the shipped policies after withDefaultPolicies()', function () {
    expect(PermissionPlugin::make()->withDefaultPolicies()->policies())->toBe([
        'Spatie\Permission\Models\Role' => RolePolicy::class,
        'Spatie\Permission\Models\Permission' => PermissionPolicy::class,
    ]);
});

it('maps a custom Spatie role model set in permission.models.role', function () {
    app(PermissionRegistrar::class)->setRoleClass(CustomRole::class);

    expect(config('permission.models.role'))->toBe(CustomRole::class)
        ->and(PermissionPlugin::make()->withDefaultPolicies()->policies())->toBe([
            CustomRole::class => RolePolicy::class,
            Permission::class => PermissionPolicy::class,
        ]);
});

it('contributes no menu of its own', function () {
    // The Resources reach the sidebar through their group, so a plugin menu would list them twice.
    expect(PermissionPlugin::make()->menu(request()))->toBeNull()
        ->and(Lathe::plugins()->menus(request()))->toBe([]);
});

it('swaps in custom resource classes', function () {
    $plugin = PermissionPlugin::make()
        ->useRoleResource(HostRoleResource::class)
        ->usePermissionResource(PermissionResource::class);

    expect($plugin->resources())->toBe([HostRoleResource::class, PermissionResource::class]);
});

it('swaps in custom policy classes', function () {
    $plugin = PermissionPlugin::make()
        ->useRolePolicy(AppRolePolicy::class)
        ->usePermissionPolicy(AppPermissionPolicy::class);

    expect($plugin->policies())->toBe([
        'Spatie\Permission\Models\Role' => AppRolePolicy::class,
        'Spatie\Permission\Models\Permission' => AppPermissionPolicy::class,
    ]);
});

it('returns the same instance from every customisation method', function () {
    $plugin = PermissionPlugin::make();

    expect($plugin->useRoleResource(RoleResource::class))->toBe($plugin)
        ->and($plugin->usePermissionResource(PermissionResource::class))->toBe($plugin)
        ->and($plugin->useRolePolicy(RolePolicy::class))->toBe($plugin)
        ->and($plugin->usePermissionPolicy(PermissionPolicy::class))->toBe($plugin)
        ->and($plugin->withDefaultPolicies())->toBe($plugin);
});

it('keeps a hand-registered instance over the discovered one', function () {
    $configured = $this->registerOnly(PermissionPlugin::make()->useRoleResource(HostRoleResource::class));
    $vendor = InstalledJson::write();

    try {
        (new PluginDiscovery($vendor))->register(Lathe::plugins());
    } finally {
        InstalledJson::forget($vendor);
    }

    expect(Lathe::plugins()->all())->toHaveCount(1)
        ->and(Lathe::plugins()->find('permission'))->toBe($configured)
        ->and(Lathe::resources()->forKey('roles'))->toBe(HostRoleResource::class);
});

/**
 * A host app that already owns the `roles` uriKey cannot boot the panel beside the plugin,
 * which is what useRoleResource() is for.
 */
it('collides with a host resource that already owns the roles uriKey', function () {
    Lathe::registerResources(HostRoleResource::class);

    expect(fn () => Lathe::resources()->forKey('roles'))->toThrow(
        ResourceUriKeyCollisionException::class,
        'Resources '.HostRoleResource::class.' and '.RoleResource::class.' share the uriKey "roles"',
    );
});

it('boots beside the host roles resource once useRoleResource() points at it', function () {
    $this->registerOnly(PermissionPlugin::make()->useRoleResource(HostRoleResource::class));
    Lathe::registerResources(HostRoleResource::class);

    expect(Lathe::resources()->forKey('roles'))->toBe(HostRoleResource::class)
        ->and(Lathe::resources()->forKey('permissions'))->toBe(PermissionResource::class);
});

it('boots beside a second host role resource that calls canonical(false)', function () {
    Lathe::registerResources(NonCanonicalRoleResource::class);

    expect(Lathe::resources()->forKey('team-roles'))->toBe(NonCanonicalRoleResource::class)
        ->and(Lathe::resources()->forModel(app(PermissionRegistrar::class)->getRoleClass()))->toBe(RoleResource::class);
});
