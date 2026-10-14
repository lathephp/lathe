<?php

use Lathe\Lathe;
use Lathe\Permission\PermissionPlugin;
use Lathe\Permission\Tests\Fixtures\ReportResource;
use Lathe\Permission\Tests\Fixtures\TestUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function permissionCacheKey(): string
{
    return config('permission.cache.key');
}

/**
 * Spatie caches the permissions on the first read; the listeners forget the key on each write.
 */
it('forgets the Spatie cache key when a role is saved', function () {
    app(PermissionRegistrar::class)->getPermissions();
    expect(cache()->has(permissionCacheKey()))->toBeTrue();

    Role::create(['name' => 'cache-test-role']);

    expect(cache()->has(permissionCacheKey()))->toBeFalse();
});

it('forgets the Spatie cache key when a role is deleted', function () {
    $role = Role::create(['name' => 'cache-delete-test']);
    app(PermissionRegistrar::class)->getPermissions();
    expect(cache()->has(permissionCacheKey()))->toBeTrue();

    $role->delete();

    expect(cache()->has(permissionCacheKey()))->toBeFalse();
});

it('forgets the Spatie cache key when a permission is saved', function () {
    app(PermissionRegistrar::class)->getPermissions();
    expect(cache()->has(permissionCacheKey()))->toBeTrue();

    Permission::create(['name' => 'cache-test-perm']);

    expect(cache()->has(permissionCacheKey()))->toBeFalse();
});

it('forgets the Spatie cache key when a permission is deleted', function () {
    $permission = Permission::create(['name' => 'cache-delete-perm']);
    app(PermissionRegistrar::class)->getPermissions();
    expect(cache()->has(permissionCacheKey()))->toBeTrue();

    $permission->delete();

    expect(cache()->has(permissionCacheKey()))->toBeFalse();
});

/**
 * The editor's next request, as a fresh user record, the way a new request loads it.
 */
function asEditor(TestUser $editor): void
{
    test()->actingAs($editor->fresh() ?? $editor);
}

describe('the editor\'s next request', function () {
    beforeEach(function () {
        // Spatie's gate hook is on by default (permission.register_permission_check_method),
        // so ReportPolicy's $user->can('view reports') reads the cached permissions.
        expect(config('permission.register_permission_check_method'))->toBeTrue();

        $this->registerOnly(PermissionPlugin::make()->withDefaultPolicies());
        Lathe::registerResources(ReportResource::class);
        $this->admin = $this->signInAdmin();
        $this->editor = TestUser::create(['name' => 'Editor', 'email' => 'editor@example.test']);
        Permission::create(['name' => 'view reports']);

        asEditor($this->editor);
        $this->getJson('/lathe/api/resources/reports')->assertForbidden();

        $this->actingAs($this->admin);
        $this->role = $this->postJson('/lathe/api/resources/roles', ['name' => 'reporter', 'permissions' => ['view reports']])->assertCreated()->json('id');
        $this->putJson("/lathe/api/resources/users/{$this->editor->id}", ['roles' => ['reporter']])->assertOk();
    });

    it('answers 200 once the admin grants the permission through a role, with no cache clear', function () {
        asEditor($this->editor);

        $this->getJson('/lathe/api/resources/reports')->assertOk();
    });

    it('shows a renamed role at once', function () {
        $this->putJson("/lathe/api/resources/roles/{$this->role}", ['name' => 'analyst'])->assertOk();

        asEditor($this->editor);
        $this->getJson('/lathe/api/resources/reports')->assertOk();

        expect(app(PermissionRegistrar::class)->getPermissions(['name' => 'view reports'])->first()?->roles->pluck('name')->all())->toBe(['analyst']);
    });

    it('answers 403 once the admin deletes the role', function () {
        $this->deleteJson("/lathe/api/resources/roles/{$this->role}")->assertSuccessful();

        asEditor($this->editor);
        $this->getJson('/lathe/api/resources/reports')->assertForbidden();
    });
});
