<?php

use Illuminate\Support\Facades\Event;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Lathe;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->artisan('migrate')->assertSuccessful();
    $this->app->register(WorkbenchLatheServiceProvider::class);
    config(['lathe.fortify.features' => [Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => false])]]);
    reloadLatheRoutes();

    $this->secret = (new Google2FA)->generateSecretKey();
    $this->admin = User::factory()->create(['email' => 'admin@lathephp.com']);
    $this->admin->forceFill([
        'two_factor_secret' => encrypt($this->secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['aaaaaaaaaa-1111111111', 'bbbbbbbbbb-2222222222'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
});

function signInWithPassword(): void
{
    test()->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe/two-factor-challenge');
}

it('sends a user with confirmed two-factor to the challenge after the password, and fires the event once', function () {
    Event::fake([TwoFactorAuthenticationChallenged::class]);

    signInWithPassword();

    $this->assertGuest();
    Event::assertDispatchedTimes(TwoFactorAuthenticationChallenged::class, 1);
});

it('answers a JSON sign-in with two_factor true', function () {
    $this->postJson('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertOk()
        ->assertExactJson(['two_factor' => true]);
});

it('signs in a user whose two-factor is still pending without a challenge', function () {
    $this->admin->forceFill(['two_factor_confirmed_at' => null])->save();

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe');
});

it('renders the challenge page after the password, and sends anyone else to sign in', function () {
    $this->get('/lathe/two-factor-challenge')->assertRedirect('/lathe/login');

    signInWithPassword();

    $this->get('/lathe/two-factor-challenge')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/TwoFactorChallenge', false));
});

it('signs in with the current code and lands on the initial path', function () {
    Lathe::initialPath('/lathe/resources/orders');
    signInWithPassword();

    $this->post('/lathe/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($this->secret)])
        ->assertRedirect('http://localhost/lathe/resources/orders');

    $this->assertAuthenticatedAs($this->admin);
});

it('refuses a wrong code with 422 under code', function () {
    signInWithPassword();

    $this->postJson('/lathe/two-factor-challenge', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'The provided two factor authentication code was invalid.']);

    $this->assertGuest();
});

it('sends a wrong code from the page back to the challenge with the error', function () {
    signInWithPassword();

    $this->from('/lathe/two-factor-challenge')
        ->post('/lathe/two-factor-challenge', ['code' => '000000'])
        ->assertRedirect('http://localhost/lathe/two-factor-challenge')
        ->assertSessionHasErrors('code');
});

it('signs in with a recovery code once; the same code fails the second time', function () {
    signInWithPassword();

    $this->post('/lathe/two-factor-challenge', ['recovery_code' => 'aaaaaaaaaa-1111111111'])
        ->assertRedirect('http://localhost/lathe');
    $this->assertAuthenticatedAs($this->admin);
    expect($this->admin->refresh()->recoveryCodes())->not->toContain('aaaaaaaaaa-1111111111')->toHaveCount(2);

    $this->post('/lathe/logout');
    signInWithPassword();

    $this->postJson('/lathe/two-factor-challenge', ['recovery_code' => 'aaaaaaaaaa-1111111111'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recovery_code');
    $this->assertGuest();
});

it('answers the 6th attempt in a minute with 429', function () {
    signInWithPassword();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/lathe/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();
    }

    $this->postJson('/lathe/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($this->secret)])
        ->assertStatus(429);
    $this->assertGuest();
});

it('answers 404 on the challenge once the feature is off', function () {
    config(['lathe.fortify.features' => [Features::updatePasswords()]]);
    reloadLatheRoutes();

    $this->get('/lathe/two-factor-challenge')->assertNotFound();
    $this->postJson('/lathe/two-factor-challenge', ['code' => '000000'])->assertNotFound();
});
