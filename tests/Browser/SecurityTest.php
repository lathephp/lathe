<?php

use Laravel\Fortify\Features;
use Lathe\Lathe;
use Pest\Browser\Api\AwaitableWebpage;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

require_once __DIR__.'/records.php';

const RECOVERY_CODES = ['aaaaaaaaaa-1111111111', 'bbbbbbbbbb-2222222222', 'cccccccccc-3333333333', 'dddddddddd-4444444444', 'eeeeeeeeee-5555555555', 'ffffffffff-6666666666', 'gggggggggg-7777777777', 'hhhhhhhhhh-8888888888'];

/** The computed direction of the first element the selector finds. */
function directionOf(string $selector): string
{
    return "getComputedStyle(document.querySelector('{$selector}')).direction";
}

/**
 * Turn on password updates and two-factor with a confirming code, and with confirmPassword
 * when asked. The workbench leaves these options to the tests.
 */
function securityFeatures(bool $confirmPassword = false): void
{
    config(['lathe.fortify.features' => [Features::updatePasswords(), Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => $confirmPassword])]]);
    reloadLatheRoutes();
}

/** Put the admin's two-factor in a state: off, pending (a secret, not yet confirmed) or on. */
function adminTwoFactor(string $state): void
{
    User::query()->where('email', 'admin@lathephp.com')->firstOrFail()->forceFill([
        'two_factor_secret' => $state === 'off' ? null : encrypt((new Google2FA)->generateSecretKey()),
        'two_factor_recovery_codes' => $state === 'on' ? encrypt(json_encode(RECOVERY_CODES)) : null,
        'two_factor_confirmed_at' => $state === 'on' ? now() : null,
    ])->save();
}

function currentAdminTotp(): string
{
    return (new Google2FA)->getCurrentOtp(decrypt(User::query()->where('email', 'admin@lathephp.com')->value('two_factor_secret')));
}

/** Fill a 6-digit code into the OTP field from its first slot, which spreads it over the six. */
function typeTotp(AwaitableWebpage $page, string $scope, string $code): AwaitableWebpage
{
    $first = "{$scope} [data-slot=\"otp-field-slot\"] >> nth=0";

    return $page->type($first, $code);
}

/**
 * Drop the browser's session cookie and the user the in-process server holds, so the next
 * request is a signed-out one.
 */
function dropSession(AwaitableWebpage $page): void
{
    $context = (fn () => $this->page)->call($page)->context();

    foreach ((fn () => $this->sendMessage('clearCookies', ['name' => config('session.cookie')]))->call($context) as $message) {
        //
    }

    forgetServerSession();
}

/** Sign in as the admin with two-factor on, up to the challenge screen. */
function atChallenge(): AwaitableWebpage
{
    adminTwoFactor('on');
    forgetServerSession();
    $page = latheVisit('/lathe/login', 'desktop-chrome');
    signInThroughForm($page, 'admin@lathephp.com');

    return $page->assertPathIs('/lathe/two-factor-challenge')->assertPresent('[data-slot="otp-field"]');
}

/** Sign in as the admin with two-factor on, through the current code, and open a panel URL. */
function signedInWithCode(string $url): AwaitableWebpage
{
    return typeTotp(atChallenge(), 'form', currentAdminTotp())->press('Sign in')->assertPathIs('/lathe')->navigate($url);
}

/** Open one screen of the Security behaviour, with the admin in the state it needs. */
function openSecurityScreen(string $screen): AwaitableWebpage
{
    securityFeatures(confirmPassword: $screen === 'confirm-dialog');

    if ($screen === 'security-off' || $screen === 'confirm-dialog') {
        adminTwoFactor('off');
    } elseif ($screen === 'security-pending') {
        adminTwoFactor('pending');
    }

    return match ($screen) {
        'security-off', 'security-pending' => signedInAt('/lathe/security')->assertSee('Two-factor authentication'),
        'security-on' => signedInWithCode('/lathe/security')->assertSee('Two-factor authentication is on.'),
        'challenge' => atChallenge(),
        'confirm-password' => signedInAt('/lathe/confirm-password')->assertSee('For your security, please confirm your password to continue.'),
        'confirm-dialog' => signedInAt('/lathe/security')->press('Enable')->assertSeeIn('[role="dialog"]', 'Confirm password'),
        'reauth-code' => (function () {
            $page = signedInWithCode('/lathe/resources/customers/new')->type('#field-name', 'After Expiry');
            dropSession($page);

            return $page->click('button[type="submit"]:text-is("Create Customer")')
                ->assertSeeIn('[role="dialog"]', 'Your session has expired')
                ->type('[role="dialog"] input[type="password"]', 'password')
                ->click('[role="dialog"] button[type="submit"]')
                ->assertPresent('[role="dialog"] [data-slot="otp-field"]');
        })(),
    };
}

$screens = ['security-off', 'security-pending', 'security-on', 'challenge', 'confirm-password', 'confirm-dialog', 'reauth-code'];

it('passes axe on the screen in light', function (string $screen) {
    $page = openSecurityScreen($screen);

    $page->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with($screens);

it('passes axe on the screen in dark', function (string $screen) {
    $page = openSecurityScreen($screen);

    // As the appearance switcher does it; the OTP slots ease their colours over 150 ms, so axe waits.
    $page->script('document.documentElement.classList.remove("light"); document.documentElement.classList.add("dark")');
    $page->assertScript('document.documentElement.classList.contains("dark")', true)
        ->wait(0.5)
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with($screens);

it('passes axe on the screen right to left', function (string $screen) {
    Lathe::enableRTL();
    $page = openSecurityScreen($screen);

    $page->assertScript('document.documentElement.dir', 'rtl')
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with($screens);

it('keeps the code digits, the QR code, the setup key and the recovery codes left to right in RTL', function (string $screen, array $selectors) {
    Lathe::enableRTL();
    $page = openSecurityScreen($screen);

    if ($screen === 'security-on') {
        $page->press('Show recovery codes');
    }

    $page->assertPresent($selectors[0])->assertScript('document.documentElement.dir', 'rtl');

    foreach ($selectors as $selector) {
        $page->assertScript(directionOf($selector), 'ltr');
    }
})->with([
    'security-pending' => ['security-pending', ['main .bg-white svg', '[data-slot="otp-field"]', 'main p.font-mono']],
    'security-on' => ['security-on', ['ul[aria-label="Recovery codes"]']],
    'challenge' => ['challenge', ['[data-slot="otp-field"]']],
    'reauth-code' => ['reauth-code', ['[role="dialog"] [data-slot="otp-field"]']],
]);

it('turns on two-factor from the user menu and signs in later with a recovery code', function () {
    securityFeatures(confirmPassword: true);
    $page = signedInAt('/lathe');

    $page->click('[data-lathe-sidebar] button[aria-haspopup="menu"]')
        ->click('[role="menuitem"]:text-is("Security")')
        ->assertPathIs('/lathe/security')
        ->press('Enable')
        ->assertSeeIn('[role="dialog"]', 'Confirm password')
        ->type('[role="dialog"] input[type="password"]', 'password')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertNotPresent('[role="dialog"]')
        ->assertPresent('main .bg-white svg');

    $secret = decrypt(User::query()->where('email', 'admin@lathephp.com')->value('two_factor_secret'));
    $page->assertSeeIn('main p.font-mono', $secret);

    typeTotp($page, 'main form:has([data-slot="otp-field"])', currentAdminTotp())
        ->press('Confirm')
        ->assertSee('Two-factor authentication is on.')
        ->press('Show recovery codes')
        ->assertCount('ul[aria-label="Recovery codes"] li', 8);

    $codes = $page->script('[...document.querySelectorAll(\'ul[aria-label="Recovery codes"] li\')].map((li) => li.textContent)');
    expect($codes)->toHaveCount(8)
        ->and($codes)->toBe(json_decode(decrypt(User::query()->where('email', 'admin@lathephp.com')->value('two_factor_recovery_codes')), true));

    // A fresh load, so the user menu opened for Security starts closed again.
    $page->navigate('/lathe')
        ->click('[data-lathe-sidebar] button[aria-haspopup="menu"]')
        ->click('[role="menuitem"]:has-text("Log out")')
        ->assertPathIs('/lathe/login');

    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe/two-factor-challenge')
        ->press('Use a recovery code')
        ->type('#recovery_code', $codes[0])
        ->press('Sign in')
        ->assertPathIs('/lathe');

    expect(json_decode(decrypt(User::query()->where('email', 'admin@lathephp.com')->value('two_factor_recovery_codes')), true))->not->toContain($codes[0]);
    assertCleanPage($page);
});
