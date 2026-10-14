<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;
use Lathe\Permission\PermissionPlugin;
use Lathe\Permission\Policies\PermissionPolicy;
use Lathe\Permission\Policies\RolePolicy;
use Lathe\Permission\Resources\PermissionResource;
use Lathe\Permission\Resources\RoleResource;
use Lathe\Permission\Tests\Fixtures\CountingRegistrar;
use Lathe\Permission\Tests\Fixtures\TestUser;
use Lathe\Permission\Tests\Fixtures\TestUserResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('registers the plugin through the test case', function () {
    expect(Lathe::plugins()->all())->toHaveCount(1)
        ->and(Lathe::plugins()->find('permission'))->toBeInstanceOf(PermissionPlugin::class);
});

it('registers RoleResource and PermissionResource during plugin boot', function () {
    expect(Lathe::resources()->forKey('roles'))->toBe(RoleResource::class)
        ->and(Lathe::resources()->forKey('permissions'))->toBe(PermissionResource::class);
});

it('registers no policy until the app opts in', function () {
    $registrar = app(PermissionRegistrar::class);

    expect(Lathe::plugins()->find('permission')?->policies())->toBe([])
        ->and(Gate::getPolicyFor($registrar->getRoleClass()))->toBeNull()
        ->and(Gate::getPolicyFor($registrar->getPermissionClass()))->toBeNull();
});

it('registers the shipped policies after withDefaultPolicies()', function () {
    $this->registerOnly(PermissionPlugin::make()->withDefaultPolicies());
    $registrar = app(PermissionRegistrar::class);

    expect(Gate::getPolicyFor($registrar->getRoleClass()))->toBeInstanceOf(RolePolicy::class)
        ->and(Gate::getPolicyFor($registrar->getPermissionClass()))->toBeInstanceOf(PermissionPolicy::class);
});

it('lets the shipped policies allow every ability to a user who passes viewLathe only', function () {
    $user = TestUser::create(['email' => 'admin@example.test']);
    $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];

    $allowed = fn (object $policy) => array_map(fn (string $ability) => $policy->{$ability}($user), $abilities);

    expect($allowed(new RolePolicy))->toBe(array_fill(0, 7, true))
        ->and($allowed(new PermissionPolicy))->toBe(array_fill(0, 7, true));

    Gate::define('viewLathe', fn () => false);

    expect($allowed(new RolePolicy))->toBe(array_fill(0, 7, false))
        ->and($allowed(new PermissionPolicy))->toBe(array_fill(0, 7, false));
});

it('binds RoleResource to the Spatie Role model in the registry', function () {
    expect(Lathe::resources()->forModel(app(PermissionRegistrar::class)->getRoleClass()))->toBe(RoleResource::class);
});

it('finds the host User resource by the auth user model', function () {
    expect(Lathe::resources()->forModel(TestUser::class))->toBe(TestUserResource::class);
});

it('flushes the Spatie cache from its own listener on the four model events', function () {
    $registrar = app(CountingRegistrar::class);
    app()->instance(PermissionRegistrar::class, $registrar);

    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $afterRoleSaved = $registrar->flushes;
    $role->delete();
    $afterRoleDeleted = $registrar->flushes;
    $permission = Permission::create(['name' => 'view orders', 'guard_name' => 'web']);
    $afterPermissionSaved = $registrar->flushes;
    $permission->delete();

    // Spatie's own RefreshesPermissionCache flushes once per event too, so each event counts 2.
    expect([$afterRoleSaved, $afterRoleDeleted, $afterPermissionSaved, $registrar->flushes])->toBe([2, 4, 6, 8]);
});
