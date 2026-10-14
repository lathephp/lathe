<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Auth\FortifyConfig;
use Lathe\Http\Requests\LatheRequest;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    config([
        'auth.providers.staff' => ['driver' => 'eloquent', 'model' => User::class],
        'auth.guards.staff' => ['driver' => 'session', 'provider' => 'staff'],
        'lathe.guard' => 'staff',
    ]);
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);
    reloadLatheRoutes();
    Gate::define('viewLathe', fn () => true);

    Route::middleware('lathe.api')->get('lathe/who', fn (LatheRequest $request) => ['email' => $request->user()?->getAttribute('email')]);
    $this->staff = User::factory()->create(['email' => 'admin@lathephp.com']);
});

it('admits a user signed in on the panel guard and refuses one on the web guard (pin)', function () {
    $this->actingAs($this->staff, 'staff')->getJson('/lathe/who')->assertOk()->assertExactJson(['email' => 'admin@lathephp.com']);

    auth()->guard('staff')->logout();

    $this->actingAs(User::factory()->create(), 'web')->getJson('/lathe/who')->assertUnauthorized();
});

it('signs in on the panel guard through the login form (pin)', function () {
    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])->assertRedirect('http://localhost/lathe');

    expect(auth()->guard('staff')->check())->toBeTrue()
        ->and(auth()->guard('web')->check())->toBeFalse();
});

it('reads the guard, model, broker and field names from config (pin)', function () {
    $fortify = app(FortifyConfig::class);

    expect($fortify->guard())->toBe('staff')
        ->and($fortify->userModel())->toBe(User::class)
        ->and($fortify->passwordBroker())->toBe('users')
        ->and($fortify->username())->toBe('email')
        ->and($fortify->email())->toBe('email');

    config(['lathe.passwords' => 'staff', 'lathe.fortify.username' => 'username']);

    expect($fortify->passwordBroker())->toBe('staff')
        ->and($fortify->username())->toBe('username');
    $this->get('/lathe/login')->assertInertia(fn (AssertableInertia $page) => $page->where('username', 'username'));
});

it('puts the host fortify config back for a host route after a panel request (pin)', function () {
    config(['fortify.guard' => 'web']);
    Route::middleware('web')->get('host-guard', fn () => ['guard' => config('fortify.guard')]);

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password']);

    $this->getJson('/host-guard')->assertExactJson(['guard' => 'web']);
});

it('fails the doctor on a missing guard, broker or user model (pin)', function (array $config, string $line) {
    config($config);

    $code = Artisan::call('lathe:doctor');

    expect($code)->toBe(1)->and(Artisan::output())->toContain($line);
})->with([
    'ghost guard' => [['lathe.guard' => 'ghost'], 'FAIL Guard ghost is not defined in config/auth.php.'],
    'missing broker' => [['lathe.passwords' => 'nowhere'], 'FAIL Password broker nowhere is not defined in config/auth.php.'],
    'provider without a model' => [['auth.providers.staff.model' => null], 'FAIL The user model of guard staff cannot be found.'],
]);

it('confirms the password of the user on the panel guard', function () {
    // Through the form: actingAs() would also make staff the default guard.
    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password']);

    $this->postJson('/lathe/confirm-password', ['password' => 'password'])->assertCreated();
});
