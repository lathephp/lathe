<?php

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Lathe\Tests\Fixtures\Models\VerifyingUser;
use Workbench\App\Models\User;
use Workbench\App\Providers\LatheServiceProvider as WorkbenchLatheServiceProvider;

beforeEach(function () {
    config([
        'lathe.fortify.features' => [Features::emailVerification()],
        'auth.providers.users.model' => VerifyingUser::class,
    ]);
    $this->app->register(FortifyServiceProvider::class);
    $this->app->register(InertiaServiceProvider::class);
    $this->loadLaravelMigrations();
    $this->app->register(WorkbenchLatheServiceProvider::class);
    reloadLatheRoutes();
    Route::middleware('lathe.api')->get('lathe/reports', fn () => 'reports');

    $this->unverified = VerifyingUser::query()->forceCreate([
        'name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => 'password', 'email_verified_at' => null,
    ]);
});

function verifyUrl(VerifyingUser $user, int $minutes = 60): string
{
    return URL::temporarySignedRoute('lathe.verification.verify', now()->addMinutes($minutes), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);
}

it('holds an unverified user on the verification screen', function () {
    $this->actingAs($this->unverified)->get('/lathe/resources/orders')->assertRedirect('/lathe/email/verify');

    $this->get('/lathe/email/verify')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/VerifyEmail', false));
});

it('answers the API with 403 for an unverified user', function () {
    $this->actingAs($this->unverified)->getJson('/lathe/api/resources/orders')
        ->assertForbidden()
        ->assertJson(['message' => 'Your email address is not verified.']);
});

it('resends the verification mail, six times a minute', function () {
    Notification::fake();
    $this->actingAs($this->unverified);

    foreach (range(1, 6) as $attempt) {
        $this->post('/lathe/email/verification-notification')->assertRedirect();
    }

    $this->post('/lathe/email/verification-notification')->assertStatus(429);
    Notification::assertSentToTimes($this->unverified, VerifyEmail::class, 6);
});

it('mails a signed panel link that verifies the email and lands on the initial path', function () {
    config(['mail.default' => 'array']);
    $this->actingAs($this->unverified)->post('/lathe/email/verification-notification');

    $body = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage()->getHtmlBody();
    expect($body)->toMatch('#http://localhost/lathe/email/verify/'.$this->unverified->getKey().'/[0-9a-f]{40}\?expires=\d+&amp;signature=[0-9a-f]{64}#');

    $this->get(verifyUrl($this->unverified))->assertRedirect('http://localhost/lathe');

    expect($this->unverified->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->get('/lathe/reports')->assertOk();
});

it('refuses a tampered, wrong, expired or borrowed link with 403', function () {
    $other = VerifyingUser::query()->forceCreate([
        'name' => 'Editor User', 'email' => 'editor@lathephp.com', 'password' => 'password', 'email_verified_at' => null,
    ]);
    $this->actingAs($this->unverified);

    $this->get(verifyUrl($this->unverified).'0')->assertForbidden();
    $this->get(URL::temporarySignedRoute('lathe.verification.verify', now()->addHour(), ['id' => $this->unverified->getKey(), 'hash' => sha1('wrong@example.test')]))->assertForbidden();

    $expired = verifyUrl($this->unverified, 1);
    $this->travel(2)->minutes();
    $this->get($expired)->assertForbidden();
    $this->travelBack();

    $this->get(verifyUrl($other))->assertForbidden();

    expect($this->unverified->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('lets a verified user through', function () {
    $this->unverified->forceFill(['email_verified_at' => now()])->save();

    $this->actingAs($this->unverified)->get('/lathe/reports')->assertOk();
});

it('never holds a user model that does not verify email', function () {
    config(['auth.providers.users.model' => User::class]);
    $user = User::query()->findOrFail($this->unverified->getKey());

    $this->actingAs($user)->get('/lathe/reports')->assertOk();
});

it('has no verification routes and holds nobody with the feature off', function () {
    config(['lathe.fortify.features' => []]);
    reloadLatheRoutes();
    Route::middleware('lathe.api')->get('lathe/reports', fn () => 'reports');

    $this->actingAs($this->unverified)->get('/lathe/reports')->assertOk();
    $this->get('/lathe/email/verify')->assertNotFound();
    $this->post('/lathe/email/verification-notification')->assertNotFound();
    $this->get('/lathe/email/verify/1/abc')->assertNotFound();
});

it('lets an unverified user sign out', function () {
    $this->actingAs($this->unverified)->post('/lathe/logout')->assertRedirect('/lathe/login');
    $this->assertGuest();
});
