<?php

use Lathe\Field\FieldContext;
use Lathe\Permission\Fields\PermissionMultiSelectField;
use Lathe\Permission\Fields\RoleMultiSelectField;
use Lathe\Permission\Fields\RoleSelectField;
use Lathe\Permission\Tests\Fixtures\TestUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The three fields read their value through resolveUsing() closures. Lathe injects the
 * record by name, so a change there would leave every field resolving to null while still
 * drawing: silent data loss on the edit form.
 */
it('resolves a role permission set into permission names', function () {
    Permission::create(['name' => 'users.create']);
    Permission::create(['name' => 'users.view']);
    $role = Role::create(['name' => 'editor']);
    $role->givePermissionTo(['users.view', 'users.create']);

    $value = (new PermissionMultiSelectField)->resolve($role->fresh());

    expect(collect($value)->sort()->values()->all())->toBe(['users.create', 'users.view']);
});

it('resolves a user role set into role names', function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'editor']);
    $user = TestUser::create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x']);
    $user->assignRole(['admin', 'editor']);

    $value = (new RoleMultiSelectField)->resolve($user->fresh());

    expect(collect($value)->sort()->values()->all())->toBe(['admin', 'editor']);
});

it('resolves a single role for the select variant', function () {
    Role::create(['name' => 'admin']);
    $user = TestUser::create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x']);
    $user->assignRole('admin');

    expect((new RoleSelectField)->resolve($user->fresh()))->toBe('admin');
});

it('resolves to nothing for a record without the Spatie traits', function () {
    // The fields go on the host's User Resource, which may not use HasRoles yet.
    expect((new PermissionMultiSelectField)->resolve(new stdClass))->toBe([])
        ->and((new RoleMultiSelectField)->resolve(new stdClass))->toBe([])
        ->and((new RoleSelectField)->resolve(new stdClass))->toBeNull();
});

it('inlines the role options ordered by name while there are few roles', function () {
    Role::create(['name' => 'editor']);
    Role::create(['name' => 'admin']);

    expect(array_column((new RoleMultiSelectField)->resolvedOptions(), 'value'))->toBe(['admin', 'editor'])
        ->and(array_column((new RoleSelectField)->resolvedOptions(), 'value'))->toBe(['admin', 'editor']);
});

it('hides the sync fields from the index but shows them on detail and forms', function (Closure $make) {
    $field = $make();

    expect(array_map(fn (FieldContext $context) => $field->isShownOn($context), [FieldContext::Index, FieldContext::Detail, FieldContext::Create, FieldContext::Update]))
        ->toBe([false, true, true, true]);
})->with([
    'permissions' => fn () => fn () => new PermissionMultiSelectField,
    'roles' => fn () => fn () => new RoleMultiSelectField,
    'role' => fn () => fn () => new RoleSelectField,
]);

it('draws each field with the select component of its kind', function (Closure $make, string $component) {
    expect($make()->getComponent())->toBe($component);
})->with([
    'permissions' => [fn () => fn () => new PermissionMultiSelectField, 'multi-select-field'],
    'roles' => [fn () => fn () => new RoleMultiSelectField, 'multi-select-field'],
    'role' => [fn () => fn () => new RoleSelectField, 'select-field'],
]);

it('names and attributes each field for use on a host User resource', function () {
    expect([(new PermissionMultiSelectField)->label(), (new PermissionMultiSelectField)->attribute()])->toBe(['Permissions', 'permissions'])
        ->and([(new RoleMultiSelectField)->label(), (new RoleMultiSelectField)->attribute()])->toBe(['Roles', 'roles'])
        ->and([(new RoleSelectField)->label(), (new RoleSelectField)->attribute()])->toBe(['Role', 'role']);
});

it('accepts a custom name and attribute', function () {
    $field = new RoleMultiSelectField('Team Roles', 'team_roles');

    expect([$field->label(), $field->attribute()])->toBe(['Team Roles', 'team_roles']);
});
