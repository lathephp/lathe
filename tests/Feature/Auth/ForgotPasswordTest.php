<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Lathe;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);

    $this->admin = User::factory()->create(['email' => 'admin@lathephp.com']);
});

it('links to Forgot password from the login screen while the workbench turns reset routes on', function () {
    expect(Lathe::hasPasswordResetRoutes())->toBeTrue();

    $this->get('/lathe/login')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('authPaths.forgotPassword', '/lathe/forgot-password'));

    $this->get('/lathe/forgot-password')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/ForgotPassword', false)
        ->where('status', null));
});

it('answers 404 on every reset route and shows no link once reset routes are off (pin)', function () {
    Lathe::withoutPasswordResetRoutes();
    reloadLatheRoutes();

    $this->get('/lathe/login')->assertInertia(fn (AssertableInertia $page) => $page->where('authPaths.forgotPassword', null));
    $this->get('/lathe/forgot-password')->assertNotFound();
    $this->post('/lathe/forgot-password', ['email' => 'admin@lathephp.com'])->assertNotFound();
    $this->get('/lathe/reset-password/x')->assertNotFound();
    $this->post('/lathe/reset-password', [])->assertNotFound();
});

it('gives a known and an unknown address the same answer, and mails only the known one', function () {
    Notification::fake();

    $known = $this->from('/lathe/forgot-password')->post('/lathe/forgot-password', ['email' => 'admin@lathephp.com']);
    $unknown = $this->from('/lathe/forgot-password')->post('/lathe/forgot-password', ['email' => 'nobody@example.test']);

    expect($known->status())->toBe($unknown->status());
    $known->assertRedirect('/lathe/forgot-password')->assertSessionHas('status', 'We have emailed your password reset link.');
    $unknown->assertRedirect('/lathe/forgot-password')->assertSessionHas('status', 'We have emailed your password reset link.');

    Notification::assertSentToTimes($this->admin, ResetPassword::class, 1);
    Notification::assertCount(1);
});

it('answers JSON the same way for a known and an unknown address', function () {
    Notification::fake();

    $this->postJson('/lathe/forgot-password', ['email' => 'admin@lathephp.com'])
        ->assertOk()->assertExactJson(['message' => 'We have emailed your password reset link.']);
    $this->postJson('/lathe/forgot-password', ['email' => 'nobody@example.test'])
        ->assertOk()->assertExactJson(['message' => 'We have emailed your password reset link.']);
});

it('sends no second mail inside the throttle, with the same answer', function () {
    Notification::fake();

    $this->postJson('/lathe/forgot-password', ['email' => 'admin@lathephp.com'])->assertOk();
    $this->postJson('/lathe/forgot-password', ['email' => 'admin@lathephp.com'])
        ->assertOk()->assertExactJson(['message' => 'We have emailed your password reset link.']);

    Notification::assertSentToTimes($this->admin, ResetPassword::class, 1);
});

it('mails a panel reset link through the host mailer and calls nothing outside', function () {
    Http::preventStrayRequests();
    config(['mail.default' => 'array']);

    $this->post('/lathe/forgot-password', ['email' => 'admin@lathephp.com']);

    $messages = app('mailer')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);

    $body = $messages[0]->getOriginalMessage()->getHtmlBody();
    expect($body)->toMatch('#http://localhost/lathe/reset-password/[A-Za-z0-9]{64}\?email=admin%40lathephp\.com#');
});

it('leaves the host reset link alone outside the panel (pin)', function () {
    $notification = new ResetPassword('token');

    expect(fn () => $notification->toMail($this->admin))->toThrow(RouteNotFoundException::class);
});

it('wants a valid email', function () {
    $this->postJson('/lathe/forgot-password', ['email' => 'not-an-email'])->assertJsonValidationErrors('email');
});
