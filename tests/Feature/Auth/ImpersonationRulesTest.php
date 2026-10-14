<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Auth\Impersonatable;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    // Untyped, so a user model other than the workbench one still reaches the endpoint.
    Gate::define('viewLathe', fn ($user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));

    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->editor = User::where('email', 'editor@lathephp.com')->firstOrFail();
});

/**
 * The admin's row as a user model that takes the trait and overrides nothing, or allows impersonating.
 */
function adminWithPlainTrait(User $admin, bool $allow = false): Authenticatable
{
    $user = $allow
        ? new class extends Authenticatable
        {
            use Impersonatable;

            protected $table = 'users';

            public function canImpersonate(): bool
            {
                return true;
            }
        }
    : new class extends Authenticatable
    {
        use Impersonatable;

        protected $table = 'users';
    };

    $user->setRawAttributes($admin->getAttributes(), true);
    $user->exists = true;

    return $user;
}

it('denies impersonating by default and allows being impersonated', function () {
    $user = adminWithPlainTrait($this->admin);

    expect($user->canImpersonate())->toBeFalse()
        ->and($user->canBeImpersonated())->toBeTrue();
});

it('answers 403 to the admin on a User model with no override, and 200 once it allows', function () {
    $this->actingAs(adminWithPlainTrait($this->admin));
    $this->postJson('/lathe/api/impersonate', ['resource' => 'users', 'resourceId' => $this->editor->id])->assertForbidden();

    $this->actingAs(adminWithPlainTrait($this->admin, allow: true));
    $this->postJson('/lathe/api/impersonate', ['resource' => 'users', 'resourceId' => $this->editor->id])->assertOk();
});

it('lets only the admin impersonate on the workbench User model', function () {
    $guest = User::where('email', 'guest@lathephp.com')->firstOrFail();

    expect($this->admin->canImpersonate())->toBeTrue()
        ->and($this->editor->canImpersonate())->toBeFalse()
        ->and($guest->canImpersonate())->toBeFalse()
        ->and($this->editor->canBeImpersonated())->toBeTrue();
});

// The row flag

/**
 * @return array<int|string, array<string, bool>>
 */
function userRowAbilities(): array
{
    $rows = test()->getJson('/lathe/api/resources/users?per_page=100')->assertOk()->json('table.rows');

    return array_column($rows, 'abilities', 'id');
}

it('flags the editor\'s row and not the admin\'s own for the admin', function () {
    $this->actingAs($this->admin);

    $abilities = userRowAbilities();

    expect($abilities[$this->editor->id]['impersonate'])->toBeTrue()
        ->and($abilities[$this->admin->id]['impersonate'])->toBeFalse();

    // Orders do not use the trait, so their rows carry no flag at all.
    $orderRows = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.rows');

    expect($orderRows[0]['abilities'])->toHaveKey('view')->not->toHaveKey('impersonate');
});

it('flags no row for the editor, who may not impersonate', function () {
    $this->actingAs($this->editor);

    $abilities = userRowAbilities();

    expect($abilities[$this->admin->id]['impersonate'])->toBeFalse()
        ->and($abilities[$this->editor->id]['impersonate'])->toBeFalse();
});

it('flags the detail record the same way', function () {
    $this->actingAs($this->admin);

    $this->get("/lathe/resources/users/{$this->editor->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('record.abilities.impersonate', true));
    $this->get("/lathe/resources/users/{$this->admin->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('record.abilities.impersonate', false));
});
