<?php

use Lathe\Permission\PermissionPlugin;
use Lathe\Permission\Tests\Fixtures\TestUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * End to end through Lathe's Resource API: route resolution by uriKey, the policies, the
 * field payloads, the validation rules and the fill closures that call Spatie's sync methods.
 */
beforeEach(function () {
    $this->registerOnly(PermissionPlugin::make()->withDefaultPolicies());
    $this->user = $this->signInAdmin();
});

/**
 * @return array<string, mixed>|null
 */
function creationField(string $resource, string $key): ?array
{
    return collect(test()->getJson("/lathe/api/resources/{$resource}/creation-fields")->assertOk()->json('fields'))->firstWhere('key', $key);
}

function useTwoGuards(): void
{
    config(['auth.guards.api' => ['driver' => 'session', 'provider' => 'users']]);
}

it('lists roles through the resource index', function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'editor']);

    $titles = array_column($this->getJson('/lathe/api/resources/roles')->assertOk()->json('table.rows'), 'title');
    sort($titles);

    expect($titles)->toBe(['admin', 'editor']);
});

it('lists permissions through the resource index', function () {
    Permission::create(['name' => 'users.view']);

    $rows = $this->getJson('/lathe/api/resources/permissions')->assertOk()->json('table.rows');

    expect(array_column($rows, 'title'))->toBe(['users.view']);
});

it('serves the permissions multi-select on the Role creation form as a remote picker', function () {
    Permission::create(['name' => 'users.view']);

    expect(creationField('roles', 'permissions'))->toMatchArray(['component' => 'multi-select-field', 'remote' => true, 'options' => []]);
});

it('inlines the roles multi-select on the Permission creation form', function () {
    Role::create(['name' => 'admin']);

    $field = creationField('permissions', 'roles');

    expect($field['component'])->toBe('multi-select-field')
        ->and(array_column($field['options'], 'value'))->toBe(['admin']);
});

it('creates a role and syncs its permissions after the save', function () {
    Permission::create(['name' => 'users.view']);
    Permission::create(['name' => 'users.create']);

    $this->postJson('/lathe/api/resources/roles', ['name' => 'editor', 'permissions' => ['users.view', 'users.create']])->assertCreated();

    expect(Role::findByName('editor')->permissions->pluck('name')->sort()->values()->all())->toBe(['users.create', 'users.view']);
});

it('creates a permission and syncs its roles after the save', function () {
    Role::create(['name' => 'admin']);

    $this->postJson('/lathe/api/resources/permissions', ['name' => 'users.delete', 'roles' => ['admin']])->assertCreated();

    expect(Permission::findByName('users.delete')->roles->pluck('name')->all())->toBe(['admin']);
});

it('holds no guard field with one configured guard', function () {
    expect(config('auth.guards'))->toHaveKeys(['web'])->toHaveCount(1)
        ->and(creationField('roles', 'guard_name'))->toBeNull()
        ->and(creationField('permissions', 'guard_name'))->toBeNull();
});

it('offers exactly the configured guards in a required select with two guards', function (string $resource) {
    useTwoGuards();

    $field = creationField($resource, 'guard_name');

    expect($field['component'])->toBe('select-field')
        ->and($field['required'])->toBeTrue()
        ->and(array_column($field['options'], 'value'))->toBe(['web', 'api']);
})->with(['roles', 'permissions']);

it('defaults the guard to permission.default_guard, else auth.defaults.guard', function (?string $permissionDefault, string $expected) {
    useTwoGuards();
    config(['auth.defaults.guard' => 'api', 'permission.default_guard' => $permissionDefault]);
    $this->actingAs($this->user, 'api');

    expect($this->getJson('/lathe/api/resources/roles/creation-fields')->assertOk()->json('defaults.guard_name'))->toBe($expected);
})->with([
    'permission.default_guard set' => ['web', 'web'],
    'auth.defaults.guard only' => [null, 'api'],
]);

/**
 * With more than one guard the select is validated against the configured guards, so the
 * write path differs from the single-guard one.
 */
it('creates a role against a chosen guard when several are configured', function () {
    useTwoGuards();

    $this->postJson('/lathe/api/resources/roles', ['name' => 'api-admin', 'guard_name' => 'api'])->assertCreated();

    expect(Role::query()->where('name', 'api-admin')->value('guard_name'))->toBe('api');
});

it('rejects a guard that is not configured', function (string $resource) {
    useTwoGuards();

    $this->postJson("/lathe/api/resources/{$resource}", ['name' => 'api-admin', 'guard_name' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('guard_name');
})->with(['roles', 'permissions']);

it('rejects a name longer than 255 characters', function (string $resource) {
    $this->postJson("/lathe/api/resources/{$resource}", ['name' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
})->with(['roles', 'permissions']);

it('rejects a duplicate name on create', function (string $resource, string $model) {
    $model::create(['name' => 'admin']);

    $this->postJson("/lathe/api/resources/{$resource}", ['name' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
})->with([
    'roles' => ['roles', Role::class],
    'permissions' => ['permissions', Permission::class],
]);

/**
 * The update rule ignores the record being edited; without that, saving a record without
 * renaming it would fail with "already taken".
 */
it('saves a record without changing its name', function (string $resource, string $model) {
    $record = $model::create(['name' => 'admin']);

    $this->putJson("/lathe/api/resources/{$resource}/{$record->id}", ['name' => 'admin'])->assertOk();
})->with([
    'roles' => ['roles', Role::class],
    'permissions' => ['permissions', Permission::class],
]);

it('rejects renaming a record onto another name', function (string $resource, string $model) {
    $model::create(['name' => 'admin']);
    $editor = $model::create(['name' => 'editor']);

    $this->putJson("/lathe/api/resources/{$resource}/{$editor->id}", ['name' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
})->with([
    'roles' => ['roles', Role::class],
    'permissions' => ['permissions', Permission::class],
]);

it('replaces a role permission set on update', function () {
    Permission::create(['name' => 'users.view']);
    Permission::create(['name' => 'users.create']);
    $role = Role::create(['name' => 'editor']);
    $role->givePermissionTo('users.view');

    $this->putJson("/lathe/api/resources/roles/{$role->id}", ['name' => 'editor', 'permissions' => ['users.create']])->assertOk();

    expect(Role::findByName('editor')->permissions->pluck('name')->all())->toBe(['users.create']);
});

it('shows a role detail page with its assigned permissions', function () {
    Permission::create(['name' => 'users.view']);
    $role = Role::create(['name' => 'editor']);
    $role->givePermissionTo('users.view');

    $fields = $this->getJson("/lathe/resources/roles/{$role->id}", ['X-Inertia' => 'true'])->assertOk()->json('props.record.fields');

    expect(collect($fields)->firstWhere('key', 'permissions')['value'])->toBe(['users.view']);
});

it('deletes a role through the resource API', function () {
    $role = Role::create(['name' => 'editor']);

    $this->deleteJson("/lathe/api/resources/roles/{$role->id}")->assertSuccessful();

    expect(Role::query()->count())->toBe(0);
});

/**
 * The users panel on a Role's detail page, found from the host's User Resource, through the
 * real relation endpoint.
 */
it('lists the users assigned to a role through the relation endpoint', function () {
    $role = Role::create(['name' => 'admin']);
    $this->user->assignRole('admin');
    TestUser::create(['email' => 'other@example.test']);

    $rows = $this->getJson("/lathe/api/resources/roles/{$role->id}/users")->assertOk()->json('rows');

    expect(array_column($rows, 'id'))->toBe([$this->user->id]);
});
