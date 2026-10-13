<?php

use Workbench\App\Models\Order;

const PALETTE_BOX = '[role="combobox"]';
const FIRST_OPTION_TITLE = 'document.querySelector("[role=option] .font-medium").textContent';

it('finds Ann in Customers and Orders, opens the customer and lists it first in recents', function (string $project) {
    // The demo orders are numbered ORD-..., so one gets a number that matches the term.
    Order::whereKey(1)->update(['number' => 'ANN-48213']);

    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe')
        ->keys('#app', 'Control+k')
        ->assertPresent('[role="dialog"]')
        ->type(PALETTE_BOX, 'ann')
        ->assertPresent('[role="group"][aria-label="Customers"]')
        ->assertPresent('[role="group"][aria-label="Orders"]')
        ->assertNoAccessibilityIssues();

    $title = $page->script(FIRST_OPTION_TITLE);

    $page->keys(PALETTE_BOX, 'Enter')
        ->assertScript('/^\/lathe\/resources\/customers\/\d+$/.test(location.pathname)')
        ->assertNotPresent('[role="dialog"]')
        ->keys('#app', 'Control+k')
        ->assertSee('Recent searches');

    expect($page->script(FIRST_OPTION_TITLE))->toBe($title);
    assertCleanPage($page);
})->with(browserProjects());
