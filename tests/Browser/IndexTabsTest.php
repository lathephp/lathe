<?php

use Lathe\SavedTabs\SavedTab;
use Workbench\App\Models\Order;

function signedInTabs(string $project, string $url = '/lathe/resources/orders')
{
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    return $page->assertPathIs('/lathe')->navigate($url);
}

/** The strip's tab keys, saved tabs as saved:{name}, in order. */
const STRIP_ORDER = "Array.from(document.querySelectorAll('nav[aria-label=\"Tabs\"] [data-tab]'), (tab) => tab.dataset.tab.startsWith('saved:') ? 'saved:' + tab.textContent : tab.dataset.tab).join(',')";

it('saves two views after the developer tabs and moves one with Alt+Left', function (string $project) {
    $page = signedInTabs($project, '/lathe/resources/orders?filter[status]=paid');

    $page->click('Save view')
        ->fill('[role="dialog"] input[maxlength="100"]', 'Paid')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSeeIn('nav[aria-label="Tabs"] li:nth-child(4) [data-tab]', 'Paid')
        ->navigate('/lathe/resources/orders?filter[status]=cancelled')
        ->click('Save view')
        ->fill('[role="dialog"] input[maxlength="100"]', 'Cancelled')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSeeIn('nav[aria-label="Tabs"] li:nth-child(5) [data-tab]', 'Cancelled')
        ->refresh()
        ->assertScript(STRIP_ORDER, 'all,pending,paid,saved:Paid,saved:Cancelled');

    expect(SavedTab::orderBy('position')->pluck('filters', 'name')->all())
        ->toBe(['Paid' => ['status' => 'paid'], 'Cancelled' => ['status' => 'cancelled']]);

    $page->keys('nav[aria-label="Tabs"] li:nth-child(5) [data-tab]', 'Alt+ArrowLeft')
        ->assertSee('Moved Cancelled to position 1')
        ->assertScript(STRIP_ORDER, 'all,pending,paid,saved:Cancelled,saved:Paid')
        ->waitForEvent('networkidle')
        ->refresh()
        ->assertScript(STRIP_ORDER, 'all,pending,paid,saved:Cancelled,saved:Paid');
    assertCleanPage($page);
})->with(browserProjects());

it('shows the pending orders under the Pending tab with its count badge', function (string $project) {
    $pending = number_format(Order::where('status', 'pending')->count());

    $page = signedInTabs($project);

    $page->click('[data-tab="pending"]')
        ->assertQueryStringHas('tab', 'pending')
        ->assertSeeIn('[data-tab="pending"]', $pending)
        ->assertSee("Showing 1 to 25 of {$pending}")
        ->assertScript("Array.from(document.querySelectorAll('table tbody > tr'), (row) => row.textContent.includes('pending')).every(Boolean)", true)
        ->click('[data-tab="all"]')
        ->assertQueryStringMissing('tab')
        ->assertSee('Showing 1 to 25 of 10,000');
    assertCleanPage($page);
})->with(browserProjects());
