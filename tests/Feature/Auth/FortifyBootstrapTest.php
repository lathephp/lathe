<?php

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Auth\FortifyBootstrap;
use Lathe\Auth\FortifyConfig;
use Lathe\Lathe;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);

    User::factory()->create(['email' => 'admin@lathephp.com']);
});

afterEach(function () {
    Fortify::$authenticateThroughCallback = null;
    Fortify::$authenticateUsingCallback = null;
    Fortify::$confirmPasswordsUsingCallback = null;
});

class RejectBlockedDomains
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (str_ends_with((string) $request->input('email'), '@blocked.test')) {
            throw ValidationException::withMessages(['email' => 'This domain is blocked.']);
        }

        return $next($request);
    }
}

it('is the FortifyConfig the panel reads, behind Lathe::fortify()', function () {
    expect(Lathe::fortify())->toBeInstanceOf(FortifyBootstrap::class)
        ->and(app(FortifyConfig::class))->toBe(Lathe::fortify())
        ->and(Lathe::fortify()->guard())->toBe('web');
});

it('runs the login through the pipeline authenticateThrough() builds', function () {
    User::factory()->create(['email' => 'mallory@blocked.test']);

    Lathe::fortify()->authenticateThrough(fn (Request $request) => [
        RejectBlockedDomains::class,
        AttemptToAuthenticate::class,
        PrepareAuthenticatedSession::class,
    ]);

    $this->postJson('/lathe/login', ['email' => 'mallory@blocked.test', 'password' => 'password'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email' => 'This domain is blocked.']);

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe');
    $this->assertAuthenticated();
});

it('checks credentials with the authenticateUsing() callback', function () {
    $admin = User::query()->where('email', 'admin@lathephp.com')->firstOrFail();

    Lathe::fortify()->authenticateUsing(fn (Request $request) => $request->input('password') === 'letmein' ? $admin : null);

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'letmein'])->assertRedirect('http://localhost/lathe');
    $this->assertAuthenticatedAs($admin);
});

it('hands the panel callbacks to Fortify only for a panel request', function () {
    $host = fn (Request $request) => null;
    Fortify::authenticateUsing($host);

    Lathe::fortify()
        ->authenticateUsing(fn (Request $request) => null)
        ->confirmPasswordsUsing(fn ($user, ?string $password) => true);

    Route::get('host-probe', fn () => [
        'using' => Fortify::$authenticateUsingCallback === $host,
        'confirm' => Fortify::$confirmPasswordsUsingCallback === null,
    ]);

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password']);

    $this->getJson('/host-probe')->assertExactJson(['using' => true, 'confirm' => true]);
    expect(Fortify::$authenticateUsingCallback)->toBe($host);
});

it('answers a JSON login with JSON, and puts the host LoginResponse binding back', function () {
    $host = new class implements LoginResponse
    {
        public function toResponse($request)
        {
            return response('host login');
        }
    };
    $this->app->instance(LoginResponse::class, $host);

    $this->postJson('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertOk()
        ->assertExactJson(['two_factor' => false]);

    expect(app(LoginResponse::class))->toBe($host);
});

it('reports features and whether the Security page has anything to show', function (array $features, bool $twoFactor, bool $security) {
    config(['lathe.fortify.features' => $features]);

    expect(Lathe::fortify()->enabled('two-factor-authentication'))->toBe($twoFactor)
        ->and(Lathe::fortify()->hasSecurityFeatures())->toBe($security);
})->with([
    'none' => [[], false, false],
    'email verification only' => [['email-verification'], false, false],
    'password updates' => [['update-passwords'], false, true],
    'two-factor' => [['two-factor-authentication'], true, true],
]);
