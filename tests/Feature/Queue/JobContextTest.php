<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lathe\Auth\FortifyConfig;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Support\JobContext;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
});

/**
 * The panel request of the signed-in admin.
 */
function jobContextRequest(): LatheRequest
{
    $admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    Auth::guard(app(FortifyConfig::class)->guard())->setUser($admin);

    return LatheRequest::createFrom(request());
}

it('carries the actor, guard, locale and timezone of the request', function () {
    Lathe::userTimezone(fn () => 'Pacific/Kiritimati');
    app()->setLocale('fr');

    $context = JobContext::capture(jobContextRequest());

    expect($context->actor)->toBe(User::where('email', 'admin@lathephp.com')->value('id'))
        ->and($context->guard)->toBe(app(FortifyConfig::class)->guard())
        ->and($context->locale)->toBe('fr')
        ->and($context->timezone)->toBe('Pacific/Kiritimati');
});

it('signs the actor in and sets the locale in the worker', function () {
    app()->setLocale('fr');
    $context = unserialize(serialize(JobContext::capture(jobContextRequest())));

    // A worker starts with no user and the app's own locale.
    Auth::forgetGuards();
    app()->setLocale('en');

    $context->restore();

    expect(Auth::id())->toBe($context->actor)
        ->and(app()->getLocale())->toBe('fr');
});

it('returns what a host captured through its restore callback', function () {
    $restored = null;
    Lathe::captureJobContext(fn () => ['tenant' => 42]);
    Lathe::restoreJobContext(function (array $extra) use (&$restored) {
        $restored = $extra['tenant'];
    });

    $context = unserialize(serialize(JobContext::capture(jobContextRequest())));
    $context->restore();

    expect($context->extra)->toBe(['tenant' => 42])
        ->and($restored)->toBe(42);
});
