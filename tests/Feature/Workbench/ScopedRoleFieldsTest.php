<?php

use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Workbench\App\Models\User;

class DeniedAccessPolicy
{
    public function viewAny(): bool
    {
        return false;
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->artisan('lathe:demo-seed', ['--orders' => 50])->assertSuccessful();

    $this->admin = User::query()->where('email', 'admin@lathephp.com')->firstOrFail();
    $this->target = User::query()->where('email', 'editor@lathephp.com')->firstOrFail();
    $this->base = ['name' => $this->target->name, 'email' => $this->target->email];
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'support']);
});

function formKeys(User $by, ?int $target = null): array
{
    $path = $target === null ? 'creation-fields' : "{$target}/update-fields";

    return collect(test()->actingAs($by)->getJson("/lathe/api/resources/users/{$path}")->assertOk()->json('fields'))->pluck('attribute')->all();
}

it('leaves Roles off the user form for a user who may not view roles', function () {
    Gate::policy(Role::class, DeniedAccessPolicy::class);

    expect(formKeys($this->admin))->not->toContain('roles')->toContain('permissions')
        ->and(formKeys($this->admin, $this->target->id))->not->toContain('roles')->toContain('permissions');
});

it('leaves Permissions off the user form for a user who may not view permissions', function () {
    Gate::policy(Permission::class, DeniedAccessPolicy::class);

    expect(formKeys($this->admin))->not->toContain('permissions')->toContain('roles');
});

it('ignores forged roles from a user who may not view roles', function () {
    Gate::policy(Role::class, DeniedAccessPolicy::class);

    $this->actingAs($this->admin)->putJson("/lathe/api/resources/users/{$this->target->id}", $this->base + ['roles' => ['admin']])->assertOk();

    expect($this->target->fresh()->getRoleNames()->all())->toBe([]);
});

it('ignores forged permissions from a user who may not view permissions', function () {
    Gate::policy(Permission::class, DeniedAccessPolicy::class);

    $this->actingAs($this->admin)->putJson("/lathe/api/resources/users/{$this->target->id}", $this->base + ['permissions' => ['view orders']])->assertOk();

    expect($this->target->fresh()->getPermissionNames()->all())->toBe([]);
});

it('answers the role picker with 403 for a user who may not view roles', function () {
    Gate::policy(Role::class, DeniedAccessPolicy::class);

    $this->actingAs($this->admin)->getJson("/lathe/api/resources/users/{$this->target->id}/roles/associatable")->assertForbidden();
});

it('lets a user who may view roles assign and remove a role', function () {
    $this->actingAs($this->admin)->putJson("/lathe/api/resources/users/{$this->target->id}", $this->base + ['roles' => ['support']])->assertOk();

    expect($this->target->fresh()->getRoleNames()->all())->toBe(['support']);

    $this->actingAs($this->admin)->putJson("/lathe/api/resources/users/{$this->target->id}", $this->base + ['roles' => []])->assertOk();

    expect($this->target->fresh()->getRoleNames()->all())->toBe([]);
});
