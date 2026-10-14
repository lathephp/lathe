<?php

use Illuminate\Support\Facades\File;

require_once __DIR__.'/records.php';

// The origins of every resource the page fetched that are not the page's own.
const FOREIGN_REQUESTS = 'performance.getEntriesByType("resource").map((entry) => new URL(entry.name).origin).filter((origin) => origin !== location.origin)';

// The paths of the font files the page fetched.
const FETCHED_FONTS = 'performance.getEntriesByType("resource").map((entry) => new URL(entry.name).pathname).filter((path) => path.endsWith(".woff2"))';

it('fetches nothing from another origin on the login, home and Orders pages', function () {
    $page = latheVisit('/lathe/login', 'desktop-chrome');

    $page->assertSee('Sign in')
        ->assertScript(FOREIGN_REQUESTS, []);

    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe')
        ->assertSee('No widgets yet')
        ->assertScript(FOREIGN_REQUESTS, []);

    $page->navigate('/lathe/resources/orders')
        ->assertSee('Showing 1 to 25')
        ->assertScript(FOREIGN_REQUESTS, []);
    assertCleanPage($page);
});

it('draws the panel in the bundled Inter Variable from its own assets', function () {
    $page = signedInAt('/lathe/resources/orders');

    $page->assertSee('Showing 1 to 25')
        ->assertScript('getComputedStyle(document.body).fontFamily.startsWith(\'"Inter Variable"\')', true)
        ->assertScript('('.FETCHED_FONTS.').length', 1)
        ->assertScript('('.FETCHED_FONTS.')[0].startsWith("/lathe/assets/")', true)
        ->assertScript('performance.getEntriesByType("resource").filter((entry) => entry.name.endsWith(".woff2"))[0].responseStatus', 200);
});

it('serves a configured host font from the host origin', function () {
    $root = public_path('fonts');
    File::ensureDirectoryExists($root);
    File::copy(dirname(__DIR__, 2).'/resources/fonts/inter-latin-wght-normal.woff2', $root.'/acme.woff2');
    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));
    config(['lathe.theme.font' => [
        'family' => 'Acme Sans',
        'fallback' => 'sans-serif',
        'files' => [['url' => '/fonts/acme.woff2', 'weight' => 400, 'style' => 'normal']],
    ]]);

    $page = signedInAt('/lathe/resources/orders');

    $page->assertSee('Showing 1 to 25')
        ->assertScript('getComputedStyle(document.documentElement).getPropertyValue("--font-sans").trim()', '"Acme Sans", sans-serif')
        ->assertScript('document.fonts.check("16px \"Acme Sans\"")', true)
        ->assertScript(FETCHED_FONTS, ['/fonts/acme.woff2'])
        ->assertScript(FOREIGN_REQUESTS, []);
    assertCleanPage($page);
});
