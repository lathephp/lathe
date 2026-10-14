<?php

use Illuminate\Support\Facades\Password;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\FortifyServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);

    $this->admin = User::factory()->create(['email' => 'admin@lathephp.com']);
    $this->token = Password::broker()->createToken($this->admin);
});

function resetWith(array $overrides = []): array
{
    return [
        'token' => test()->token,
        'email' => 'admin@lathephp.com',
        'password' => 'new-password-2026',
        'password_confirmation' => 'new-password-2026',
        ...$overrides,
    ];
}

it('shows the reset screen with the email from the link', function () {
    $this->get("/lathe/reset-password/{$this->token}?email=admin%40lathephp.com")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/ResetPassword', false)
            ->where('token', $this->token)
            ->where('email', 'admin@lathephp.com'));
});

it('sets the new password and sends the user to sign in', function () {
    $this->post('/lathe/reset-password', resetWith())
        ->assertRedirect('/lathe/login')
        ->assertSessionHas('status', 'Your password has been reset.');

    $this->get('/lathe/login')->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'Your password has been reset.'));

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'new-password-2026'])->assertRedirect('http://localhost/lathe');
    $this->assertAuthenticatedAs($this->admin);
});

it('wants a long enough, confirmed password', function (array $overrides) {
    $this->postJson('/lathe/reset-password', resetWith($overrides))
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
})->with([
    'too short' => [['password' => 'short', 'password_confirmation' => 'short']],
    'not confirmed' => [['password_confirmation' => 'something-else-2026']],
]);

it('refuses a token that expired, read from the host broker config', function (int $expire, int $minutes) {
    config(['auth.passwords.users.expire' => $expire]);
    // The broker manager keeps the broker it built in beforeEach, with the old expiry.
    app()->forgetInstance('auth.password');
    Password::clearResolvedInstance('auth.password');
    $this->token = Password::broker()->createToken($this->admin);

    $this->travel($minutes)->minutes();

    $this->from('/lathe/reset-password/x')->post('/lathe/reset-password', resetWith())
        ->assertRedirect('/lathe/reset-password/x')
        ->assertSessionHasErrors(['email' => 'This password reset token is invalid.']);
})->with([
    'default 60 minutes' => [60, 61],
    'set to 5 minutes' => [5, 6],
]);

it('refuses a token that was already used', function () {
    $this->post('/lathe/reset-password', resetWith())->assertSessionHasNoErrors();

    $this->postJson('/lathe/reset-password', resetWith(['password' => 'another-one-2026', 'password_confirmation' => 'another-one-2026']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email' => 'This password reset token is invalid.']);
});

it('reads the broker lathe.passwords names', function () {
    config([
        'auth.passwords.staff' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'lathe.passwords' => 'staff',
    ]);
    $token = Password::broker('staff')->createToken($this->admin);

    $this->postJson('/lathe/reset-password', resetWith(['token' => $token]))->assertOk()
        ->assertExactJson(['message' => 'Your password has been reset.']);
});
