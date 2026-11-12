<?php

use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn ($user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
    config(['lathe.fortify.features' => [Features::updatePasswords(), Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])]]);
    reloadLatheRoutes();
    // A request that needs a recent password confirmation, as a requirePassword() action does.
    Route::middleware(['lathe.api', RequirePassword::using('lathe.password.confirm')])
        ->post('lathe/api/test-confirmed', fn () => response()->json(['ok' => true]));
    Route::getRoutes()->refreshNameLookups();

    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->editor = User::where('email', 'editor@lathephp.com')->firstOrFail();
});

function startImpersonatingEditor(): void
{
    test()->postJson('/lathe/api/impersonate', ['resource' => 'users', 'resourceId' => test()->editor->id])->assertOk();
}

it('refuses the password and two-factor routes while impersonating, with the password confirmed', function (string $method, string $uri) {
    $this->actingAs($this->admin);
    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();
    startImpersonatingEditor();
    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();

    $this->json($method, $uri, ['current_password' => 'password', 'password' => 'n3w-Passw0rd!', 'password_confirmation' => 'n3w-Passw0rd!', 'code' => '123456'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'Security settings are not available while impersonating.']);

    expect($this->editor->refresh()->two_factor_secret)->toBeNull();
})->with([
    'password update' => ['PUT', '/lathe/user-security/password'],
    'enable two-factor' => ['POST', '/lathe/user/two-factor-authentication'],
    'confirm two-factor' => ['POST', '/lathe/user/confirmed-two-factor-authentication'],
    'disable two-factor' => ['DELETE', '/lathe/user/two-factor-authentication'],
    'QR code' => ['GET', '/lathe/user/two-factor-qr-code'],
    'secret key' => ['GET', '/lathe/user/two-factor-secret-key'],
    'recovery codes' => ['GET', '/lathe/user/two-factor-recovery-codes'],
    'regenerate recovery codes' => ['POST', '/lathe/user/two-factor-recovery-codes'],
]);

it('keeps the target\'s recovery codes from an admin who confirmed their own password', function () {
    $this->editor->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt(json_encode(['code-1'])), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($this->admin);
    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();

    startImpersonatingEditor();

    $this->getJson('/lathe/user/two-factor-recovery-codes')->assertForbidden()->assertDontSee('code-1');
});

it('forgets the password confirmation when an impersonation starts', function () {
    $this->actingAs($this->admin);
    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();
    $this->travel(1)->minutes();

    startImpersonatingEditor();

    $this->postJson('/lathe/api/test-confirmed')->assertStatus(423);
});

it('forgets the password confirmation when an impersonation stops, so the admin confirms once again', function () {
    $this->actingAs($this->admin);
    startImpersonatingEditor();
    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();

    $this->deleteJson('/lathe/api/impersonate')->assertOk();

    $this->postJson('/lathe/api/test-confirmed')->assertStatus(423);
    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();
    $this->postJson('/lathe/api/test-confirmed')->assertOk()->assertExactJson(['ok' => true]);
});

it('keeps the admin\'s own Security routes as they were when nobody is impersonated', function () {
    $this->actingAs($this->admin);

    $this->postJson('/lathe/user/two-factor-authentication')->assertStatus(423);
    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();
    $this->postJson('/lathe/user/two-factor-authentication')->assertOk();
    $this->getJson('/lathe/user/two-factor-recovery-codes')->assertOk();
    $this->get('/lathe/security')->assertOk();
    $this->putJson('/lathe/user-security/password', ['current_password' => 'password', 'password' => 'n3w-Passw0rd!', 'password_confirmation' => 'n3w-Passw0rd!'])
        ->assertSuccessful();
});
