<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lathe\Events\ResourceCreated;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Permission\Fields\PermissionMultiSelectField;
use Lathe\Permission\Fields\RoleMultiSelectField;
use Lathe\Permission\Fields\RoleSelectField;
use Lathe\Permission\PermissionPlugin;
use Lathe\Permission\Tests\Fixtures\TestUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @param  array<string, mixed>  $input
 */
function syncRequest(array $input): LatheRequest
{
    return LatheRequest::create('/', 'POST', $input);
}

/**
 * @return list<string>
 */
function rolePermissionNames(string $role): array
{
    return Role::findByName($role)->permissions->pluck('name')->sort()->values()->all();
}

beforeEach(function () {
    Permission::create(['name' => 'view orders']);
    Permission::create(['name' => 'edit orders']);
});

it('syncs the permissions of a JSON string once its callback runs', function () {
    $role = Role::create(['name' => 'editor']);

    $after = (new PermissionMultiSelectField)->fill(syncRequest(['permissions' => '["view orders","edit orders"]']), $role);

    expect(rolePermissionNames('editor'))->toBe([]);

    $after();

    expect(rolePermissionNames('editor'))->toBe(['edit orders', 'view orders']);
});

it('syncs the permissions of an array and drops blanks', function () {
    $role = Role::create(['name' => 'editor']);

    (new PermissionMultiSelectField)->fill(syncRequest(['permissions' => ['view orders', '', null]]), $role)();

    expect(rolePermissionNames('editor'))->toBe(['view orders']);
});

it('syncs the roles of a user from the multi-select', function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'editor']);
    $user = TestUser::create(['email' => 'a@example.test']);

    (new RoleMultiSelectField)->fill(syncRequest(['roles' => '["admin","editor"]']), $user)();

    expect($user->fresh()?->getRoleNames()->sort()->values()->all())->toBe(['admin', 'editor']);
});

it('syncs one role from the select and none from null', function () {
    Role::create(['name' => 'editor']);
    $user = TestUser::create(['email' => 'a@example.test']);

    (new RoleSelectField)->fill(syncRequest(['role' => 'editor']), $user)();
    $one = $user->fresh()?->getRoleNames()->all();

    (new RoleSelectField)->fill(syncRequest(['role' => null]), $user)();

    expect([$one, $user->fresh()?->getRoleNames()->all()])->toBe([['editor'], []]);
});

it('syncs nothing when the attribute is absent from the request', function () {
    $role = Role::create(['name' => 'editor']);
    $role->givePermissionTo('view orders');

    expect((new PermissionMultiSelectField)->fill(syncRequest(['name' => 'editor']), $role))->toBeNull()
        ->and((new RoleMultiSelectField)->fill(syncRequest([]), $role))->toBeNull()
        ->and((new RoleSelectField)->fill(syncRequest([]), $role))->toBeNull()
        ->and(rolePermissionNames('editor'))->toBe(['view orders']);
});

describe('through the Resource API', function () {
    beforeEach(function () {
        $this->registerOnly(PermissionPlugin::make()->withDefaultPolicies());
        $this->signInAdmin();
    });

    it('syncs the permissions of a role it creates', function () {
        $this->postJson('/lathe/api/resources/roles', ['name' => 'editor', 'permissions' => ['view orders', 'edit orders']])->assertCreated();

        expect(rolePermissionNames('editor'))->toBe(['edit orders', 'view orders']);
    });

    it('keeps the permissions of a role renamed without them', function () {
        $role = Role::create(['name' => 'editor']);
        $role->givePermissionTo(['view orders', 'edit orders']);

        $this->putJson("/lathe/api/resources/roles/{$role->id}", ['name' => 'author'])->assertOk();

        expect(rolePermissionNames('author'))->toBe(['edit orders', 'view orders']);
    });

    /**
     * The sync runs inside the save transaction, so an error after it leaves no pivot row.
     */
    it('leaves no pivot row when the create rolls back after the sync', function () {
        $pivotsBeforeRollback = null;
        Event::listen(ResourceCreated::class, function () use (&$pivotsBeforeRollback) {
            $pivotsBeforeRollback = DB::table('role_has_permissions')->count();

            throw new RuntimeException('The create fails after the sync.');
        });

        $this->postJson('/lathe/api/resources/roles', ['name' => 'editor', 'permissions' => ['view orders', 'edit orders']])->assertServerError();

        expect($pivotsBeforeRollback)->toBe(2)
            ->and(DB::table('role_has_permissions')->count())->toBe(0)
            ->and(Role::query()->count())->toBe(0);
    });

    it('shows the role a user was saved with on the user detail page', function () {
        Role::create(['name' => 'editor']);
        $user = TestUser::create(['email' => 'editor@example.test']);

        $this->putJson("/lathe/api/resources/users/{$user->id}", ['roles' => ['editor']])->assertOk();

        $fields = $this->getJson("/lathe/resources/users/{$user->id}", ['X-Inertia' => 'true'])->assertOk()->json('props.record.fields');

        expect(collect($fields)->firstWhere('key', 'roles')['value'])->toBe(['editor']);
    });
});
