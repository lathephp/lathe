<?php

use Lathe\Lathe;
use Pest\Browser\Api\AwaitableWebpage;

require_once __DIR__.'/records.php';

const BANNER = '[data-lathe-impersonation]';

/** From the Users index, start impersonating Editor User through the row menu. */
function impersonateEditorFromUsers(AwaitableWebpage $page): AwaitableWebpage
{
    return $page->click('[aria-label="Actions for Editor User"]')
        ->click('[role="menuitem"]:text-is("Impersonate")')
        ->assertPathIs('/lathe')
        ->assertVisible(BANNER);
}

/** The axe violations inside the banner alone, at every impact level. */
function bannerViolations(AwaitableWebpage $page): mixed
{
    return $page->script('async () => (await window.axe.run(document.querySelector("'.addslashes(BANNER).'"))).violations.map((v) => v.id)');
}

it('impersonates Editor User from the Users row menu and stops back on the Users index', function () {
    $page = impersonateEditorFromUsers(signedInAt('/lathe/resources/users'));

    $page->assertSeeIn(BANNER, 'You are currently impersonating Editor User.')
        ->click('[data-lathe-sidebar] button[aria-haspopup="menu"]')
        ->assertSee('Editor User')
        ->assertSee('editor@lathephp.com')
        ->click(BANNER.' button:text-is("Stop Impersonating")')
        ->assertPathIs('/lathe/resources/users')
        ->assertNotPresent(BANNER);
    assertCleanPage($page);
});

it('keeps the banner free of axe violations in light, dark and right to left', function () {
    Lathe::enableRTL();
    $page = impersonateEditorFromUsers(signedInAt('/lathe/resources/users'));

    expect($page->script('document.documentElement.dir'))->toBe('rtl')
        ->and(bannerViolations($page))->toBe([]);

    $page->click('[data-lathe-sidebar] button[aria-haspopup="menu"]')
        ->click('[role="menuitemradio"]:has-text("Dark")')
        ->assertScript('document.documentElement.classList.contains("dark")', true);

    expect(bannerViolations($page))->toBe([]);
});

it('keeps the banner free of axe violations left to right', function () {
    $page = impersonateEditorFromUsers(signedInAt('/lathe/resources/users'));

    expect($page->script('document.documentElement.dir'))->toBe('ltr')
        ->and(bannerViolations($page))->toBe([]);
});

it('shows the panel as the editor sees it while impersonating', function () {
    $page = impersonateEditorFromUsers(signedInAt('/lathe/resources/users'));

    // OrderPolicy denies the editor delete.
    $page->navigate('/lathe/resources/orders')
        ->click('table tbody tr [aria-label^="Actions for "]')
        ->assertPresent('[role="menuitem"]:text-is("View")')
        ->assertNotPresent('[role="menuitem"]:text-is("Delete")')
        ->assertVisible(BANNER);

    $page->navigate('/lathe/resources/products/1')
        ->assertPresent('section[aria-label="Tags"] tbody tr')
        ->assertNotPresent('section[aria-label="Tags"] button:text-is("Attach")');

    // OrderPolicy keeps the editor out of the order form, so no Customer create entry shows.
    $page->navigate('/lathe/resources/orders/new')
        ->assertSee('Forbidden')
        ->assertNotPresent('button[aria-label="Create Customer"]')
        ->assertVisible(BANNER);
});
