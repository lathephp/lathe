<?php

use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->artisan('migrate')->assertSuccessful();
    $this->app->register(WorkbenchLatheServiceProvider::class);
    twoFactorOptions(['confirm' => true, 'confirmPassword' => false]);

    $this->admin = User::factory()->create(['email' => 'admin@lathephp.com']);
});

/**
 * @param  array<string, bool>  $options
 */
function twoFactorOptions(array $options): void
{
    config(['lathe.fortify.features' => [Features::updatePasswords(), Features::twoFactorAuthentication($options)]]);
    reloadLatheRoutes();
}

function currentTotp(User $user): string
{
    return (new Google2FA)->getCurrentOtp(decrypt($user->refresh()->two_factor_secret));
}

function securityTwoFactorState(): string
{
    $state = null;

    test()->get('/lathe/security')->assertOk()->assertInertia(function (AssertableInertia $page) use (&$state) {
        $page->component('Security', false);
        $state = $page->toArray()['props']['twoFactor'];
    });

    return $state;
}

it('answers 423 to enable, disable and the recovery codes until the password is confirmed, with confirmPassword', function () {
    twoFactorOptions(['confirm' => true, 'confirmPassword' => true]);
    $this->actingAs($this->admin);

    $this->postJson('/lathe/user/two-factor-authentication')->assertStatus(423);
    $this->deleteJson('/lathe/user/two-factor-authentication')->assertStatus(423);
    $this->getJson('/lathe/user/two-factor-recovery-codes')->assertStatus(423);
    $this->postJson('/lathe/user/two-factor-recovery-codes')->assertStatus(423);
    expect($this->admin->refresh()->two_factor_secret)->toBeNull();

    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();

    $this->postJson('/lathe/user/two-factor-authentication')->assertOk();
    expect($this->admin->refresh()->two_factor_secret)->not->toBeNull();
});

it('enables at once without confirmPassword, even with confirm on', function () {
    $this->actingAs($this->admin);

    $this->postJson('/lathe/user/two-factor-authentication')->assertOk();

    expect($this->admin->refresh()->two_factor_secret)->not->toBeNull();
});

it('starts Off, and enabling with confirm leaves it Pending across a reload', function () {
    $this->actingAs($this->admin);

    expect(securityTwoFactorState())->toBe('off');

    $this->postJson('/lathe/user/two-factor-authentication')->assertOk();

    expect(securityTwoFactorState())->toBe('pending');
    expect(securityTwoFactorState())->toBe('pending');
});

it('gives the QR code and the secret key while Pending', function () {
    $this->actingAs($this->admin);
    $this->postJson('/lathe/user/two-factor-authentication')->assertOk();

    $svg = $this->getJson('/lathe/user/two-factor-qr-code')->assertOk()->json('svg');
    $secret = $this->getJson('/lathe/user/two-factor-secret-key')->assertOk()->json('secretKey');

    expect($svg)->toStartWith('<svg')
        ->and($secret)->toBe(decrypt($this->admin->refresh()->two_factor_secret));
});

it('refuses a wrong code with 422 under code, and the right code turns two-factor On', function () {
    $this->actingAs($this->admin);
    $this->postJson('/lathe/user/two-factor-authentication')->assertOk();

    $this->postJson('/lathe/user/confirmed-two-factor-authentication', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'The provided two factor authentication code was invalid.']);
    expect(securityTwoFactorState())->toBe('pending');

    $this->postJson('/lathe/user/confirmed-two-factor-authentication', ['code' => currentTotp($this->admin)])->assertOk();

    expect(securityTwoFactorState())->toBe('on')
        ->and($this->admin->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('lists 8 recovery codes, and regenerating gives 8 new ones', function () {
    $this->actingAs($this->admin);
    $this->postJson('/lathe/user/two-factor-authentication')->assertOk();
    $this->postJson('/lathe/user/confirmed-two-factor-authentication', ['code' => currentTotp($this->admin)])->assertOk();

    $old = $this->getJson('/lathe/user/two-factor-recovery-codes')->assertOk()->json();

    expect($old)->toHaveCount(8)->each->toMatch('/^[A-Za-z0-9]{10}-[A-Za-z0-9]{10}$/');

    $new = $this->postJson('/lathe/user/two-factor-recovery-codes')->assertOk()->json();

    expect($new)->toHaveCount(8)->each->toMatch('/^[A-Za-z0-9]{10}-[A-Za-z0-9]{10}$/')
        ->and(array_intersect($old, $new))->toBe([])
        ->and($this->getJson('/lathe/user/two-factor-recovery-codes')->json())->toBe($new);
});

it('disables two-factor: back to Off with every two-factor column null', function () {
    $this->actingAs($this->admin);
    $this->postJson('/lathe/user/two-factor-authentication')->assertOk();
    $this->postJson('/lathe/user/confirmed-two-factor-authentication', ['code' => currentTotp($this->admin)])->assertOk();

    $this->deleteJson('/lathe/user/two-factor-authentication')->assertOk();

    expect(securityTwoFactorState())->toBe('off');
    $this->admin->refresh();
    expect($this->admin->two_factor_secret)->toBeNull()
        ->and($this->admin->two_factor_recovery_codes)->toBeNull()
        ->and($this->admin->two_factor_confirmed_at)->toBeNull();
});

it('turns On straight away when confirm is off', function () {
    twoFactorOptions(['confirm' => false, 'confirmPassword' => false]);
    $this->actingAs($this->admin);

    $this->postJson('/lathe/user/two-factor-authentication')->assertOk();

    expect(securityTwoFactorState())->toBe('on');
});

it('answers 404 on every two-factor route once the feature is off', function () {
    config(['lathe.fortify.features' => [Features::updatePasswords()]]);
    reloadLatheRoutes();
    $this->actingAs($this->admin);

    $this->postJson('/lathe/user/two-factor-authentication')->assertNotFound();
    $this->deleteJson('/lathe/user/two-factor-authentication')->assertNotFound();
    $this->postJson('/lathe/user/confirmed-two-factor-authentication', ['code' => '000000'])->assertNotFound();
    $this->getJson('/lathe/user/two-factor-qr-code')->assertNotFound();
    $this->getJson('/lathe/user/two-factor-secret-key')->assertNotFound();
    $this->getJson('/lathe/user/two-factor-recovery-codes')->assertNotFound();
    $this->postJson('/lathe/user/two-factor-recovery-codes')->assertNotFound();
});

it('sends a guest to sign in', function () {
    $this->postJson('/lathe/user/two-factor-authentication')->assertUnauthorized();
});

it('has TOTP as the only two-factor method: no email-code route', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'two-factor') || str_contains((string) $route->getName(), 'two-factor'))
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($uris)->toBe([
        'DELETE lathe/user/two-factor-authentication',
        'GET|HEAD lathe/two-factor-challenge',
        'GET|HEAD lathe/user/two-factor-qr-code',
        'GET|HEAD lathe/user/two-factor-recovery-codes',
        'GET|HEAD lathe/user/two-factor-secret-key',
        'POST lathe/two-factor-challenge',
        'POST lathe/user/confirmed-two-factor-authentication',
        'POST lathe/user/two-factor-authentication',
        'POST lathe/user/two-factor-recovery-codes',
    ]);
});
