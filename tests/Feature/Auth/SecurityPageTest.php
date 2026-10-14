<?php

use Illuminate\Support\Facades\Gate;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Lathe;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    config(['lathe.fortify.features' => [Features::updatePasswords(), Features::twoFactorAuthentication(['confirm' => true])]]);
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);
    reloadLatheRoutes();

    $this->admin = User::factory()->create(['email' => 'admin@lathephp.com']);
});

function securityWithFeatures(array $features): void
{
    config(['lathe.fortify.features' => $features]);
    reloadLatheRoutes();
}

it('renders the Security page in the shell, linked from the user menu', function () {
    $this->actingAs($this->admin)->get('/lathe/security')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Security', false)
            ->where('features', ['updatePasswords' => true, 'twoFactorAuthentication' => true])
            ->where('userMenu', fn ($items) => collect($items)->contains(fn ($item) => $item['label'] === 'Security' && $item['href'] === '/lathe/security')));

    expect(Lathe::fortify()->hasSecurityFeatures())->toBeTrue();
});

it('answers 404 with no menu entry when nothing is there to manage', function (array $features) {
    securityWithFeatures($features);

    $this->actingAs($this->admin)->get('/lathe/security')->assertNotFound();
    $this->get('/lathe')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('userMenu', fn ($items) => ! collect($items)->contains('label', 'Security')));
})->with([
    'no features' => [[]],
    'only email verification' => [[Features::emailVerification()]],
]);

it('shows only the cards whose feature is on', function () {
    securityWithFeatures([Features::updatePasswords()]);

    $this->actingAs($this->admin)->get('/lathe/security')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('features', ['updatePasswords' => true, 'twoFactorAuthentication' => false]));
});

it('changes the password, after which only the new one signs in', function () {
    $this->actingAs($this->admin)->from('/lathe/security')->put('/lathe/user-security/password', [
        'current_password' => 'password',
        'password' => 'new-password-2026',
        'password_confirmation' => 'new-password-2026',
    ])->assertRedirect('/lathe/security')->assertSessionHas('success', 'Password updated.');

    auth()->logout();

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'new-password-2026'])->assertRedirect('http://localhost/lathe');
});

it('refuses a wrong current password, a short one and a mismatch', function (array $input, string $key) {
    $this->actingAs($this->admin)->putJson('/lathe/user-security/password', [
        'current_password' => 'password',
        'password' => 'new-password-2026',
        'password_confirmation' => 'new-password-2026',
        ...$input,
    ])->assertStatus(422)->assertJsonValidationErrors($key);
})->with([
    'wrong current password' => [['current_password' => 'not-it'], 'current_password'],
    'too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'not confirmed' => [['password_confirmation' => 'something-else-2026'], 'password'],
]);

it('has no password route without updatePasswords', function () {
    securityWithFeatures([Features::twoFactorAuthentication()]);

    $this->actingAs($this->admin)->putJson('/lathe/user-security/password', [])->assertNotFound();
});

it('keeps the Security page behind viewLathe', function () {
    Gate::define('viewLathe', fn () => false);

    $this->actingAs($this->admin)->get('/lathe/security')->assertForbidden();
});
