<?php

use Workbench\App\Models\Order;

function signedInOrders(string $project, string $url = '/lathe/resources/orders')
{
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    return $page->assertPathIs('/lathe')->navigate($url);
}

it('searches by order number through the toolbar box', function (string $project) {
    Order::whereKey(1)->update(['number' => 'ZX-48213']);

    $page = signedInOrders($project);

    $page->assertSee('Showing 1 to 25 of 10,000')
        ->type('[aria-label="Search orders"]', 'ZX-48213')
        ->assertQueryStringHas('q', 'ZX-48213')
        ->assertCount('table tbody > tr', 1)
        ->assertSeeIn('table tbody > tr:first-child', 'ZX-48213');
    assertCleanPage($page);
})->with(browserProjects());

it('sorts by Total descending on the second header click', function (string $project) {
    Order::whereKey(2)->update(['number' => 'ZX-99999', 'total_cents' => 99_999_999]);

    $page = signedInOrders($project);

    $page->assertSee('Showing 1 to 25 of 10,000')
        ->click('Total')
        ->assertQueryStringHas('sort', 'total_cents')
        ->assertAttribute('th[aria-sort="ascending"]', 'aria-sort', 'ascending')
        ->click('Total')
        ->assertQueryStringHas('sort', '-total_cents')
        ->assertAttribute('th[aria-sort="descending"]', 'aria-sort', 'descending')
        ->assertSeeIn('table tbody > tr:first-child', 'ZX-99999');
    assertCleanPage($page);
})->with(browserProjects());

it('drops the page when the search changes and Back restores page 3', function (string $project) {
    $page = signedInOrders($project, '/lathe/resources/orders?page=3');

    $page->assertSee('Showing 51 to 75 of 10,000')
        ->type('[aria-label="Search orders"]', 'ORD-0')
        ->assertQueryStringHas('q', 'ORD-0')
        ->assertQueryStringMissing('page')
        ->assertSee('Showing 1 to 25')
        ->back()
        ->assertQueryStringHas('page', '3')
        ->assertQueryStringMissing('q')
        ->assertSee('Showing 51 to 75 of 10,000');
    assertCleanPage($page);
})->with(browserProjects());

it('shows a refused sort as a banner with status 422', function (string $project) {
    $page = signedInOrders($project, '/lathe/resources/orders?sort=password');

    $page->assertSeeIn('[role="alert"]', 'sort is invalid')
        ->assertScript('fetch(location.href).then((response) => response.status)', 422)
        ->assertCount('table', 0)
        ->click('Reset')
        ->assertQueryStringMissing('sort')
        ->assertSee('Showing 1 to 25 of 10,000');
    assertCleanPage($page);
})->with(browserProjects());

it('shows a page past the cap as a banner with no rows', function (string $project) {
    $page = signedInOrders($project, '/lathe/resources/orders?page=401');

    $page->assertSeeIn('[role="alert"]', 'Narrow the filters')
        ->assertScript('fetch(location.href).then((response) => response.status)', 422)
        ->assertCount('table tbody > tr', 0)
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with(browserProjects());
