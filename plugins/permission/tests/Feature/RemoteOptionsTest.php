<?php

use Illuminate\Support\Facades\DB;
use Lathe\Permission\Fields\RoleMultiSelectField;
use Lathe\Permission\Fields\RoleSelectField;
use Lathe\Permission\PermissionPlugin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->registerOnly(PermissionPlugin::make()->withDefaultPolicies());
    $this->signInAdmin();
});

/**
 * @return list<string>
 */
function numberedNames(string $prefix, int $count): array
{
    return array_map(fn (int $i) => sprintf('%s %03d', $prefix, $i), range(1, $count));
}

it('builds the Role creation form without reading the permissions table', function () {
    foreach (numberedNames('order', 30) as $name) {
        Permission::create(['name' => $name]);
    }

    // Spatie's gate hook reads the permissions once per cache fill while the policy asks
    // viewLathe; warm the cache so only the form itself is watched.
    app(PermissionRegistrar::class)->getPermissions();

    $reads = [];
    DB::listen(function ($query) use (&$reads) {
        if (str_contains($query->sql, '"permissions"')) {
            $reads[] = $query->sql;
        }
    });

    $fields = $this->getJson('/lathe/api/resources/roles/creation-fields')->assertOk()->json('fields');

    expect($reads)->toBe([])
        ->and(collect($fields)->firstWhere('key', 'permissions'))->toMatchArray(['remote' => true, 'options' => []]);
});

it('searches the permissions by name, 25 at a time', function () {
    foreach ([...array_reverse(numberedNames('order', 30)), ...numberedNames('item', 5)] as $name) {
        Permission::create(['name' => $name]);
    }

    $json = $this->getJson('/lathe/api/resources/roles/associatable/permissions?q=ord')->assertOk()->json();

    expect($json)->toBe([
        'items' => array_map(fn (string $name) => ['value' => $name, 'display' => $name], numberedNames('order', 25)),
        'has_more' => true,
    ]);
});

it('inlines both role fields at 100 roles', function () {
    foreach (numberedNames('role', 100) as $name) {
        Role::create(['name' => $name]);
    }

    $roles = collect($this->getJson('/lathe/api/resources/permissions/creation-fields')->assertOk()->json('fields'))->firstWhere('key', 'roles');

    expect($roles)->not->toHaveKey('remote')
        ->and(array_column($roles['options'], 'value'))->toBe(numberedNames('role', 100))
        ->and((new RoleSelectField)->isRemote())->toBeFalse()
        ->and((new RoleMultiSelectField)->isRemote())->toBeFalse();
});

it('switches both role fields to the remote source at 101 roles', function () {
    foreach (numberedNames('role', 101) as $name) {
        Role::create(['name' => $name]);
    }

    $roles = collect($this->getJson('/lathe/api/resources/permissions/creation-fields')->assertOk()->json('fields'))->firstWhere('key', 'roles');

    expect($roles)->toMatchArray(['remote' => true, 'options' => []])
        ->and((new RoleSelectField)->isRemote())->toBeTrue()
        ->and((new RoleMultiSelectField)->isRemote())->toBeTrue();
});
