<?php

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->artisan('lathe:demo-seed', ['--orders' => 50])->assertSuccessful();
});

function workbenchUser(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

it('seeds the five permissions and no role, again on a second run', function () {
    $this->artisan('lathe:demo-seed', ['--orders' => 50])->assertSuccessful();

    expect(Permission::query()->orderBy('id')->pluck('name')->all())
        ->toBe(['view orders', 'edit orders', 'delete orders', 'view posts', 'view system status'])
        ->and(Role::query()->count())->toBe(0);
});

it('opens System Status to the admin, and to the editor only once granted the permission', function () {
    $admin = workbenchUser('admin@lathephp.com');
    $editor = workbenchUser('editor@lathephp.com');

    $this->actingAs($admin)->get('/lathe/pages/system-status')->assertOk();
    $this->actingAs($editor)->get('/lathe/pages/system-status')->assertForbidden();

    $editor->givePermissionTo('view system status');

    $this->actingAs($editor->fresh())->get('/lathe/pages/system-status')->assertOk();
});

it('gives the Users form role and permission pickers', function () {
    $fields = collect($this->actingAs(workbenchUser('admin@lathephp.com'))
        ->getJson('/lathe/api/resources/users/creation-fields')->assertOk()->json('fields'))
        ->keyBy('attribute');

    expect($fields)->toHaveKeys(['roles', 'permissions'])
        ->and($fields['roles']['label'])->toBe('Roles')
        ->and($fields['permissions']['label'])->toBe('Permissions')
        ->and($fields['permissions']['remote'])->toBeTrue();
});

it('wipes roles and permission grants with the demo tables', function () {
    workbenchUser('editor@lathephp.com')->givePermissionTo('view orders');
    Role::create(['name' => 'editor']);

    $this->artisan('lathe:demo-seed', ['--orders' => 50])->assertSuccessful();

    expect(Role::query()->count())->toBe(0)
        ->and(DB::table('model_has_permissions')->count())->toBe(0);
});
