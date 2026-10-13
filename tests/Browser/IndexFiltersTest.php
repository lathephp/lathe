<?php

use Workbench\App\Models\Order;
use Workbench\App\Models\Post;

function signedInIndexFilters(string $project, string $url)
{
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    return $page->assertPathIs('/lathe')->navigate($url);
}

it('filters Orders by Status only on Apply and keeps it on reload', function (string $project) {
    $pending = number_format(Order::where('status', 'pending')->count());
    $onlyPending = "[...document.querySelectorAll('table tbody > tr')].every((row) => row.textContent.includes('pending'))";

    $page = signedInIndexFilters($project, '/lathe/resources/orders');

    $page->assertSee('Showing 1 to 25 of 10,000')
        ->click('Filters')
        ->select('status', 'pending')
        ->assertScript('location.search', '')
        ->assertSee('Showing 1 to 25 of 10,000')
        ->click('Apply')
        ->assertSee("Showing 1 to 25 of {$pending}")
        ->assertScript('decodeURIComponent(location.search)', '?filter[status]=pending')
        ->assertCount('table tbody > tr', 25)
        ->assertScript($onlyPending, true)
        ->refresh()
        ->assertSee("Showing 1 to 25 of {$pending}")
        ->assertScript('decodeURIComponent(location.search)', '?filter[status]=pending')
        ->assertScript($onlyPending, true);
    assertCleanPage($page);
})->with(browserProjects());

it('lists only trashed posts with a Soft Deleted badge', function (string $project) {
    expect(Post::onlyTrashed()->count())->toBe(10);

    $page = signedInIndexFilters($project, '/lathe/resources/posts');

    $page->assertCount('table tbody > tr', 25)
        ->click('Filters')
        ->select('trashed', 'only')
        ->assertQueryStringHas('trashed', 'only')
        ->assertCount('table tbody > tr', 10)
        ->assertSeeIn('table tbody > tr:first-child', 'Soft Deleted');
    assertCleanPage($page);
})->with(browserProjects());
