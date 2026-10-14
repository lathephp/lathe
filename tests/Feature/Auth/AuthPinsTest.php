<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Tests\Fixtures\Models\VerifyingUser;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);

    $this->admin = User::factory()->create(['email' => 'admin@lathephp.com']);
});

it('answers GET on the logout URL with 405 (pin)', function () {
    $this->actingAs($this->admin)->get('/lathe/logout')->assertStatus(405);

    $this->assertAuthenticated();
});

it('signs out on POST and sends the browser to the login page (pin)', function () {
    $this->actingAs($this->admin)->post('/lathe/logout')->assertRedirect('/lathe/login');

    $this->assertGuest();
});

it('answers a JSON login with two_factor false (pin)', function () {
    $this->postJson('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertOk()
        ->assertExactJson(['two_factor' => false]);
});

it('answers a JSON logout with 204 (pin)', function () {
    $this->actingAs($this->admin)->postJson('/lathe/logout')->assertNoContent();

    $this->assertGuest();
});

it('answers a JSON reset link request with the sent message (pin)', function () {
    $this->postJson('/lathe/forgot-password', ['email' => 'admin@lathephp.com'])
        ->assertOk()
        ->assertExactJson(['message' => 'We have emailed your password reset link.']);
});

it('answers a JSON password reset with the reset message (pin)', function () {
    $token = Password::broker()->createToken($this->admin);

    $this->postJson('/lathe/reset-password', [
        'token' => $token,
        'email' => 'admin@lathephp.com',
        'password' => 'new-password-2026',
        'password_confirmation' => 'new-password-2026',
    ])->assertOk()->assertExactJson(['message' => 'Your password has been reset.']);
});

it('answers a JSON email verification with 204 (pin)', function () {
    config([
        'lathe.fortify.features' => [Features::emailVerification()],
        'auth.providers.users.model' => VerifyingUser::class,
    ]);
    reloadLatheRoutes();
    $user = VerifyingUser::query()->forceCreate([
        'name' => 'Verify Me', 'email' => 'verify@lathephp.com', 'password' => 'password', 'email_verified_at' => null,
    ]);
    $url = URL::temporarySignedRoute('lathe.verification.verify', now()->addHour(), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->getJson($url)->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('throttles the sixth login attempt for one email and address (pin)', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'wrong'])->assertStatus(422);
    }

    $message = $this->postJson('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'wrong'])
        ->assertStatus(429)
        ->json('message');

    expect($message)->toMatch('/^Too many login attempts\. Please try again in \d+ seconds\.$/');
    preg_match('/in (\d+) seconds/', $message, $seconds);
    expect((int) $seconds[1])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(60);
});
