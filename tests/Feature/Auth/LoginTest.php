<?php

use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\FortifyServiceProvider;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    // Testbench skips package discovery under PHPUnit, so the providers a host gets from it are registered here.
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);

    User::factory()->create(['email' => 'admin@lathephp.com']);
});

it('sends a signed-out visitor to the login page and back to the page they asked for', function () {
    Route::middleware('lathe.api')->get('lathe/reports', fn () => 'reports');

    $this->get('/lathe/reports')->assertRedirect('/lathe/login');

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe/reports');

    $this->get('/lathe/reports')->assertOk()->assertSeeText('reports');
});

it('redirects a signed-out visit to the panel root to the login page', function () {
    $this->get('/lathe')->assertRedirect('/lathe/login');
});

it('renders the login page as the Auth/Login Inertia page', function () {
    $this->get('/lathe/login')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login', false)
            ->where('username', 'email'));
});

it('lands the admin on the panel root after signing in', function () {
    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe');

    $this->assertAuthenticated();
    $this->get('/lathe')->assertOk();
});

it('shows a wrong password as an error under the email field', function () {
    $this->from('/lathe/login')
        ->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'wrong'])
        ->assertRedirect('/lathe/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('answers a wrong password with 422 and errors.email for JSON', function () {
    $this->postJson('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('throttles the sixth login attempt in a minute for one username and IP', function () {
    // A host may name a login limiter that only Fortify's own routes apply, or a pipeline without the throttle.
    config([
        'fortify.limiters.login' => 'login',
        'fortify.pipelines.login' => [AttemptToAuthenticate::class, PrepareAuthenticatedSession::class],
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    $response = $this->postJson('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'wrong'])
        ->assertStatus(429);

    $message = $response->json('errors.email.0');
    expect($message)->toMatch('/^Too many login attempts\. Please try again in \d+ seconds\.$/');

    preg_match('/in (\d+) seconds/', $message, $matches);
    expect((int) $matches[1])->toBeGreaterThan(0);

    $this->postJson('/lathe/login', ['email' => 'other@lathephp.com', 'password' => 'wrong'])
        ->assertStatus(422);
});

it('signs out on POST /lathe/logout and goes back to the login page', function () {
    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password']);
    $this->assertAuthenticated();

    $this->post('/lathe/logout')->assertRedirect('/lathe/login');

    $this->assertGuest();
    $this->get('/lathe')->assertRedirect('/lathe/login');
});

it('refuses GET /lathe/logout with 405', function () {
    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password']);

    $this->get('/lathe/logout')->assertStatus(405);
    $this->assertAuthenticated();
});

it('keeps Fortify routes off the host root', function () {
    expect(Route::has('login'))->toBeFalse()
        ->and(Route::has('logout'))->toBeFalse();

    $this->post('/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])->assertNotFound();
});

it('signs in on the panel guard and puts the host fortify config back afterwards', function () {
    config([
        'auth.guards.panel' => ['driver' => 'session', 'provider' => 'users'],
        'lathe.guard' => 'panel',
        'fortify.guard' => 'web',
        'fortify.username' => 'login_name',
        'fortify.limiters.login' => 'login',
        'fortify.redirects.login' => '/host-home',
    ]);
    $host = config('fortify');

    $this->post('/lathe/login', ['email' => 'admin@lathephp.com', 'password' => 'password'])
        ->assertRedirect('http://localhost/lathe');

    expect(auth('panel')->check())->toBeTrue()
        ->and(auth('web')->check())->toBeFalse()
        ->and(config('fortify'))->toBe($host);
});
