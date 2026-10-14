<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Models\VerifyingUser;
use Pest\Browser\Api\AwaitableWebpage;
use Workbench\App\Models\User;

require_once __DIR__.'/records.php';

/** The brand name, the card under it and the hook under the card: their order and where they sit. */
const AUTH_LAYOUT = <<<'JS'
    (() => {
        const brand = document.querySelector('main h1');
        const card = brand.nextElementSibling;
        const hook = card.nextElementSibling;
        const box = card.getBoundingClientRect();

        return {
            brand: brand.textContent,
            cards: document.querySelectorAll('main [data-slot="card"]').length,
            hook: hook.getAttribute('data-lathe-hook'),
            badge: hook.querySelectorAll('[data-demo-hook="auth:after-form"]').length,
            below: Math.round(hook.getBoundingClientRect().top - box.bottom) >= 0,
            centred: Math.abs((box.left + box.right) / 2 - document.documentElement.clientWidth / 2) <= 1,
            width: Math.round(box.width) <= 384,
        };
    })()
    JS;

/**
 * The path of an auth screen that takes no sign-in, with a real reset token for the reset one.
 */
function authScreenUrl(string $screen): string
{
    $token = $screen === 'reset' ? Password::broker()->createToken(User::query()->where('email', 'admin@lathephp.com')->firstOrFail()) : '';

    return match ($screen) {
        'login' => '/lathe/login',
        'forgot' => '/lathe/forgot-password',
        'reset' => "/lathe/reset-password/{$token}?email=admin%40lathephp.com",
        'verify' => throw new InvalidArgumentException('The verify screen needs a sign-in.'),
    };
}

/**
 * Turn email verification on and hold the admin on the verification screen.
 */
function holdAdminUnverified(): void
{
    config([
        'lathe.fortify.features' => [Features::emailVerification()],
        'auth.providers.users.model' => VerifyingUser::class,
    ]);
    reloadLatheRoutes();
    User::query()->where('email', 'admin@lathephp.com')->update(['email_verified_at' => null]);
}

function visitAuthScreen(string $screen, string $project = 'desktop-chrome'): AwaitableWebpage
{
    forgetServerSession();

    if ($screen === 'verify') {
        holdAdminUnverified();
        $page = latheVisit('/lathe/login', $project);
        signInThroughForm($page, 'admin@lathephp.com');

        return $page->assertPathIs('/lathe/email/verify');
    }

    return latheVisit(authScreenUrl($screen), $project);
}

$screens = ['login', 'forgot', 'reset', 'verify'];

it('centres one card under the brand name with the hook below it', function (string $screen, string $project) {
    $page = visitAuthScreen($screen, $project);

    // The hook content comes from a plugin script that loads after the page.
    $page->assertPresent('[data-demo-hook="auth:after-form"]');

    expect($page->script(AUTH_LAYOUT))->toBe([
        'brand' => config('app.name'),
        'cards' => 1,
        'hook' => 'auth:after-form',
        'badge' => 1,
        'below' => true,
        'centred' => true,
        'width' => true,
    ]);
    assertFitsViewport($page);
    assertCleanPage($page);
})->with($screens)->with(browserProjects());

it('passes axe on the screen in light', function (string $screen) {
    $page = visitAuthScreen($screen);

    $page->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with($screens);

it('passes axe on the screen in dark', function (string $screen) {
    $page = visitAuthScreen($screen);

    $page->script('document.documentElement.classList.add("dark")');
    $page->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with($screens);

it('passes axe on the screen right to left', function (string $screen) {
    Lathe::enableRTL();
    $page = visitAuthScreen($screen);

    $page->assertPresent('[data-demo-hook="auth:after-form"]')
        ->assertScript('document.documentElement.dir', 'rtl')
        ->assertNoAccessibilityIssues();
    expect($page->script(AUTH_LAYOUT))->toMatchArray(['centred' => true, 'below' => true]);
    assertCleanPage($page);
})->with($screens);

it('shows a malformed email under the field on the forgot screen', function () {
    $page = visitAuthScreen('forgot');

    $page->fill('email', 'not-an-email')
        ->press('Email password reset link')
        ->assertSeeIn('#email-error', 'The email field must be a valid email address.')
        ->assertAttribute('#email', 'aria-describedby', 'email-error')
        ->wait(0.5)
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
});

it('shows a short password under the field on the reset screen', function () {
    $page = visitAuthScreen('reset');

    $page->fill('password', 'short')
        ->fill('password_confirmation', 'short')
        ->press('Reset password')
        ->assertSeeIn('#password-error', 'The password field must be at least 8 characters.')
        ->assertAttribute('#password', 'aria-describedby', 'password-error')
        ->wait(0.5)
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
});

it('resets the password from the link in the mail log', function () {
    $folder = sys_get_temp_dir().'/lathe-auth-mail-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($folder);
    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($folder));
    config([
        'mail.default' => 'log',
        'mail.mailers.log.channel' => 'auth-mail',
        'logging.channels.auth-mail' => ['driver' => 'single', 'path' => $folder.'/mail.log'],
    ]);
    app('mail.manager')->forgetMailers();

    $page = visitAuthScreen('forgot');
    $page->fill('email', 'admin@lathephp.com')
        ->press('Email password reset link')
        ->assertSee('We have emailed your password reset link.');

    expect($folder.'/mail.log')->toBeFile();
    preg_match('#/lathe/reset-password/[A-Za-z0-9]+\?email=admin%40lathephp\.com#', file_get_contents($folder.'/mail.log'), $link);
    expect($link)->toHaveCount(1);

    $page->navigate($link[0])
        ->assertValue('#email', 'admin@lathephp.com')
        ->fill('password', 'a-brand-new-password')
        ->fill('password_confirmation', 'a-brand-new-password')
        ->press('Reset password')
        ->assertPathIs('/lathe/login')
        ->assertSee('Your password has been reset.');

    signInThroughForm($page, 'admin@lathephp.com', 'a-brand-new-password');

    $page->assertPathIs('/lathe');
    expect(Hash::check('a-brand-new-password', User::query()->where('email', 'admin@lathephp.com')->value('password')))->toBeTrue();
    assertCleanPage($page);
});
