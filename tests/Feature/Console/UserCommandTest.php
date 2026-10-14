<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
});

it('user_command_works_with_no_interaction', function () {
    $this->artisan('lathe:user', ['--name' => 'Grace Hopper', '--email' => 'grace@example.test', '--password' => 'secret-pass', '--no-interaction' => true])
        ->assertExitCode(0);

    $user = User::where('email', 'grace@example.test')->firstOrFail();
    expect($user->name)->toBe('Grace Hopper')
        ->and(Hash::check('secret-pass', $user->password))->toBeTrue();
})->group('regression');

it('prints every error of the set and exits 1', function () {
    $this->artisan('lathe:user', ['--name' => 'A', '--email' => 'nope', '--password' => 'short', '--no-interaction' => true])
        ->expectsOutputToContain('name field must be at least 2')
        ->expectsOutputToContain('email field must be a valid email')
        ->expectsOutputToContain('password field must be at least 8')
        ->assertExitCode(1);

    expect(User::where('email', 'nope')->exists())->toBeFalse();
});

it('refuses an email already on the user table', function () {
    $this->artisan('lathe:user', ['--name' => 'Admin', '--email' => 'admin@lathephp.com', '--password' => 'secret-pass', '--no-interaction' => true])
        ->expectsOutputToContain('email has already been taken')
        ->assertExitCode(1);
});

it('names the missing option without a terminal', function () {
    $this->artisan('lathe:user', ['--name' => 'Grace Hopper', '--no-interaction' => true])
        ->expectsOutputToContain('--email, --password')
        ->assertExitCode(1);
});

it('prints the gate line when viewLathe does not admit the new user', function () {
    $this->artisan('lathe:user', ['--name' => 'Grace Hopper', '--email' => 'grace@example.test', '--password' => 'secret-pass', '--no-interaction' => true])
        ->expectsOutputToContain("'grace@example.test',")
        ->assertExitCode(0);

    Gate::define('viewLathe', fn () => true);

    $this->artisan('lathe:user', ['--name' => 'Ada Lovelace', '--email' => 'ada@example.test', '--password' => 'secret-pass', '--no-interaction' => true])
        ->doesntExpectOutputToContain("'ada@example.test',")
        ->assertExitCode(0);
});
