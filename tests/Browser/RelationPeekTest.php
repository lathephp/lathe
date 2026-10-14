<?php

require_once __DIR__.'/records.php';

// The card over the Orders list: its own opaque background, and inside the viewport.
it('opens a readable customer card inside the viewport on hover', function (string $project) {
    $page = signedInAt('/lathe/resources/orders', $project);

    // The action menus load after the rows; a hover before they settle can lose the card.
    $page->assertPresent('main button[aria-haspopup="menu"]')
        ->hover('main tbody tr:first-child a[href*="/resources/customers/"]')
        ->assertPresent('[role="tooltip"]')
        ->assertScript('getComputedStyle(document.querySelector(\'[role="tooltip"]\')).backgroundColor !== "rgba(0, 0, 0, 0)"', true)
        ->assertScript('(() => { const box = document.querySelector(\'[role="tooltip"]\').getBoundingClientRect(); return box.left >= 0 && box.right <= window.innerWidth; })()', true);
    assertCleanPage($page);
})->with(browserProjects());
