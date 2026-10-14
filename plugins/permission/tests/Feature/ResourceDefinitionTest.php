<?php

use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\MorphToManyField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Permission\Resources\PermissionResource;
use Lathe\Permission\Resources\RoleResource;
use Lathe\Permission\Tests\Fixtures\TestUserResource;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * @param  class-string<RoleResource|PermissionResource>  $resource
 * @return list<Field>
 */
function permissionResourceFields(string $resource): array
{
    return array_values((new $resource)->fields(LatheRequest::create('/')));
}

/**
 * The definition is read back through its getters, so a renamed builder method fails here
 * instead of quietly dropping the sidebar group, the icon or the search columns.
 */
it('configures the Role resource definition', function () {
    $definition = (new RoleResource)->definition();

    expect($definition->getModel())->toBe(app(PermissionRegistrar::class)->getRoleClass())
        ->and($definition->getTitle())->toBe('name')
        ->and($definition->getSearchable())->toBe(['id', 'name'])
        ->and($definition->getGroup())->toBe('Access Control')
        ->and($definition->getIcon())->toBe('shield-check');
});

it('configures the Permission resource definition', function () {
    $definition = (new PermissionResource)->definition();

    expect($definition->getModel())->toBe(app(PermissionRegistrar::class)->getPermissionClass())
        ->and($definition->getTitle())->toBe('name')
        ->and($definition->getSearchable())->toBe(['id', 'name'])
        ->and($definition->getGroup())->toBe('Access Control')
        ->and($definition->getIcon())->toBe('key');
});

it('names its uriKey and labels', function () {
    expect(RoleResource::uriKey())->toBe('roles')
        ->and(RoleResource::label())->toBe('Roles')
        ->and(RoleResource::singularLabel())->toBe('Role')
        ->and(PermissionResource::uriKey())->toBe('permissions')
        ->and(PermissionResource::label())->toBe('Permissions')
        ->and(PermissionResource::singularLabel())->toBe('Permission');
});

it('titles a record by its name', function () {
    $role = Role::create(['name' => 'admin']);

    expect(RoleResource::make($role)->title())->toBe('admin');
});

it('orders the fields id, name, the other side, users, created at', function (string $resource, string $other) {
    expect(array_map(fn (Field $field) => $field->attribute(), permissionResourceFields($resource)))
        ->toBe(['id', 'name', $other, 'users', 'created_at']);
})->with([
    'roles' => [RoleResource::class, 'permissions'],
    'permissions' => [PermissionResource::class, 'roles'],
]);

it('sorts on id, name and created at, and keeps created at off the forms', function (string $resource) {
    $fields = collect(permissionResourceFields($resource))->keyBy(fn (Field $field) => $field->attribute());

    expect([$fields['id']->isSortable(), $fields['name']->isSortable(), $fields['created_at']->isSortable()])->toBe([true, true, true])
        ->and($fields['created_at']->isShownOn(FieldContext::Create))->toBeFalse()
        ->and($fields['created_at']->isShownOn(FieldContext::Update))->toBeFalse()
        ->and($fields['created_at']->isShownOn(FieldContext::Detail))->toBeTrue();
})->with([RoleResource::class, PermissionResource::class]);

/**
 * The users field is found through the registry: a change to MorphToManyField or to
 * forModel() would drop it without an error, since it is omitted when the lookup fails.
 */
it('wires a detail-only MorphToMany users field at the host User resource', function (string $resource) {
    $users = collect(permissionResourceFields($resource))->first(fn (Field $field) => $field->attribute() === 'users');

    expect($users)->toBeInstanceOf(MorphToManyField::class)
        ->and($users->label())->toBe(TestUserResource::label())
        ->and($users->relatedResource())->toBe(TestUserResource::class)
        ->and($users->kind())->toBe('morphToMany')
        ->and(array_map(fn (FieldContext $context) => $users->isShownOn($context), [FieldContext::Index, FieldContext::Detail, FieldContext::Create, FieldContext::Update]))
        ->toBe([false, true, false, false]);
})->with([RoleResource::class, PermissionResource::class]);

it('omits the users field when no resource is registered for the user model', function (string $resource) {
    config(['auth.providers.users.model' => null]);

    expect(collect(permissionResourceFields($resource))->first(fn (Field $field) => $field->attribute() === 'users'))->toBeNull();
})->with([RoleResource::class, PermissionResource::class]);
