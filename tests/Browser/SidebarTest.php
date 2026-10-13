<?php

const SIDEBAR_WIDTH = "document.getElementById('lathe-sidebar').getBoundingClientRect().width";

it('collapses the sidebar to a rail that a reload keeps', function (string $project) {
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    if (str_starts_with($project, 'phone')) {
        // The toggle is a desktop control; a phone has the sheet only.
        $page->assertMissing('[aria-label="Toggle sidebar"]');

        return;
    }

    $page->assertScript(SIDEBAR_WIDTH, 256)
        ->click('[aria-label="Toggle sidebar"]')
        ->assertAttribute('#lathe-sidebar', 'data-state', 'collapsed')
        ->assertScript(SIDEBAR_WIDTH, 48);

    $cookie = browserCookie($page, 'sidebar_state');

    expect($cookie)->not->toBeNull()
        ->and($cookie['value'])->toBe('false')
        ->and($cookie['path'])->toBe('/')
        ->and($cookie['sameSite'])->toBe('Lax')
        ->and($cookie['expires'] - time())->toBeGreaterThan(364 * 86400)->toBeLessThanOrEqual(365 * 86400 + 1);

    $page->refresh()
        ->assertAttribute('#lathe-sidebar', 'data-state', 'collapsed')
        ->assertScript(SIDEBAR_WIDTH, 48);
    assertCleanPage($page);
})->with(browserProjects());

it('opens the sidebar as a sheet from the header on a narrow screen', function (string $project) {
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    if (str_starts_with($project, 'desktop')) {
        $page->resize(767, 900);
    }

    $page->assertAttribute('#lathe-sidebar', 'data-mobile', 'closed')
        ->assertScript("document.getElementById('lathe-sidebar').getBoundingClientRect().right <= 0")
        ->click('[aria-label="Open menu"]')
        ->assertAttribute('#lathe-sidebar', 'data-mobile', 'open')
        ->assertScript("document.getElementById('lathe-sidebar').getBoundingClientRect().left === 0")
        ->click('Orders')
        ->assertPathIs('/lathe/resources/orders')
        ->assertAttribute('#lathe-sidebar', 'data-mobile', 'closed');
    assertCleanPage($page);
})->with(browserProjects());

it('makes Skip to content the first tab stop', function (string $project) {
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe')
        ->refresh()
        ->assertSee('Dashboard')
        ->keys('#app', 'Tab')
        ->assertScript('document.activeElement.textContent', 'Skip to content');
    assertCleanPage($page);
})->with(browserProjects());
