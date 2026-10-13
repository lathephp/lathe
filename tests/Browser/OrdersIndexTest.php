<?php

use Workbench\App\Models\Order;

function ordersPage(string $project, string $url = '/lathe/resources/orders')
{
    $page = latheVisit($url, $project);
    signInThroughForm($page, 'admin@lathephp.com');

    return $page->assertPathIs('/lathe/resources/orders');
}

it('lists the first 25 orders with the total', function (string $project) {
    $page = ordersPage($project);

    $page->assertSee('Showing 1 to 25 of 10,000')
        ->assertCount('table tbody > tr', 25)
        ->assertScript(<<<'JS'
            [...document.querySelectorAll('#lathe-sidebar nav > div')]
                .filter((section) => section.querySelector('h2')?.textContent === 'Resources')
                .flatMap((section) => [...section.querySelectorAll('a')].map((link) => link.textContent.trim()))
                .includes('Orders')
            JS)
        ->assertNoAccessibilityIssues();
    assertFitsViewport($page);
    assertCleanPage($page);
})->with(browserProjects());

it('shows 100 rows for per_page=100', function (string $project) {
    $page = ordersPage($project, '/lathe/resources/orders?per_page=100');

    $page->assertSee('Showing 1 to 100 of 10,000')
        ->assertCount('table tbody > tr', 100);
    assertCleanPage($page);
})->with(browserProjects());

it('shows skeleton rows while page 2 loads', function (string $project) {
    $page = ordersPage($project);
    $page->assertSee('Showing 1 to 25 of 10,000');

    // Records the click, how many skeleton rows show, and when the real rows replace them.
    $page->script(<<<'JS'
        (() => {
            window.__skeleton = {};
            document.addEventListener('click', () => { window.__skeleton.clickedAt = performance.now(); }, { capture: true, once: true });
            new MutationObserver(() => {
                window.__skeleton.rows = Math.max(window.__skeleton.rows ?? 0, document.querySelectorAll('tr[data-skeleton]').length);
                const shown = document.querySelector('tr[data-skeleton]') !== null;
                if (shown && window.__skeleton.shownAt === undefined) window.__skeleton.shownAt = performance.now();
                if (!shown && window.__skeleton.shownAt !== undefined && window.__skeleton.hiddenAt === undefined) window.__skeleton.hiddenAt = performance.now();
            }).observe(document.body, { childList: true, subtree: true });
        })()
        JS);

    $page->click('Next')
        ->assertSee('Showing 26 to 50 of 10,000')
        ->assertCount('table tbody > tr', 25)
        // The panel holds the skeleton for at least 300 ms after the request starts, so it never flickers.
        ->assertScript('window.__skeleton.rows', 25)
        ->assertScript('window.__skeleton.hiddenAt - window.__skeleton.clickedAt >= 300');
    assertCleanPage($page);
})->with(browserProjects());

it('shows the empty state when there are no orders', function (string $project) {
    Order::query()->delete();

    $page = ordersPage($project);

    $page->assertSee('No orders yet')
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with(browserProjects());
