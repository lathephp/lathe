<?php

use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

/*
 * The live demo prints its sign-in for everyone. With LATHE_DEMO true, no visitor may
 * change or delete the three seeded accounts, so nobody locks the others out until the
 * nightly reset.
 */

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();

    foreach (['admin', 'editor', 'guest'] as $name) {
        UserFactory::new()->create(['name' => ucfirst($name).' User', 'email' => "{$name}@lathephp.com"]);
    }

    $this->actingAs(User::query()->where('email', 'admin@lathephp.com')->firstOrFail());
});

afterEach(fn () => putenv('LATHE_DEMO'));

/**
 * The id of a seeded account.
 */
function demoAccountId(string $email): int
{
    return (int) User::query()->where('email', $email)->value('id');
}

/**
 * The Users edit form's payload that sets a new password on the account.
 */
function demoAccountUpdate(string $email): array
{
    return [
        'name' => 'Locked Out',
        'email' => $email,
        'password' => 'taken-over',
        'password_confirmation' => 'taken-over',
    ];
}

it('refuses to change or delete the seeded accounts when LATHE_DEMO is true', function (string $email) {
    putenv('LATHE_DEMO=true');
    $id = demoAccountId($email);

    $this->putJson("/lathe/api/resources/users/{$id}", demoAccountUpdate($email))->assertForbidden();
    $this->deleteJson("/lathe/api/resources/users/{$id}")->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $id, 'email' => $email, 'name' => ucfirst(strstr($email, '@', true)).' User']);
})->with(['admin@lathephp.com', 'editor@lathephp.com', 'guest@lathephp.com'])->group('demo');

it('lets the admin change and delete the other seeded accounts without it', function () {
    $editor = demoAccountId('editor@lathephp.com');
    $guest = demoAccountId('guest@lathephp.com');

    $this->putJson("/lathe/api/resources/users/{$editor}", demoAccountUpdate('editor@lathephp.com'))->assertOk();
    $this->deleteJson("/lathe/api/resources/users/{$guest}")->assertOk();

    $this->assertDatabaseHas('users', ['id' => $editor, 'name' => 'Locked Out'])
        ->assertDatabaseMissing('users', ['id' => $guest]);
})->group('demo');
