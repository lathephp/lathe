<?php

use Workbench\App\Models\Order;

it('opens the Pending Orders lens from the toolbar switcher', function (string $project) {
    $pending = Order::where('status', 'pending')->count();

    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe')
        ->navigate('/lathe/resources/orders')
        ->assertSee('Showing 1 to 25 of')
        ->select('[aria-label="View"]', 'pending-orders')
        ->assertPathIs('/lathe/resources/orders/lens/pending-orders')
        ->waitForText('Showing 1 to 25 of '.number_format($pending))
        ->assertSee('Pending Orders')
        ->assertSee('Showing 1 to 25 of '.number_format($pending));

    // The lens has no Status column, so each listed number is checked against the pending orders.
    $numbers = $page->script('[...document.querySelectorAll("table tbody > tr > td:first-child")].map((cell) => cell.textContent.trim())');

    expect($numbers)->toHaveCount(25)
        ->and(Order::whereIn('number', $numbers)->where('status', 'pending')->count())->toBe(25);
    assertCleanPage($page);
})->with(browserProjects());
