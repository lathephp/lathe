<?php

use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;

function indexRowsPage(string $project, string $url)
{
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, 'admin@lathephp.com');

    return $page->assertPathIs('/lathe')->navigate($url);
}

/** The title of the first body row, read from its menu button, since a badge can share the first cell. */
function firstRowTitle($page): string
{
    return (string) $page->script("document.querySelector('table tbody tr [aria-label^=\"Actions for \"]').getAttribute('aria-label').slice('Actions for '.length)");
}

it('reads a capped count once the orders pass 10,000', function (string $project) {
    $page = indexRowsPage($project, '/lathe/resources/orders');
    $page->assertSee('Showing 1 to 25 of 10,000');

    DB::table('orders')->insert([
        'customer_id' => 1,
        'number' => 'ORD-X0010001',
        'status' => 'paid',
        'total_cents' => 1000,
        'placed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $page->refresh()
        ->assertSee('Showing 1 to 25 of 10,000+');
    assertCleanPage($page);
})->with(browserProjects());

it('reads 50 rows per page from the per-page menu', function (string $project) {
    $page = indexRowsPage($project, '/lathe/resources/orders');

    $page->assertSee('Showing 1 to 25 of 10,000')
        ->select('#per-page', '50')
        ->assertQueryStringHas('per_page', '50')
        ->assertSee('Showing 1 to 50 of 10,000')
        ->assertCount('table tbody > tr', 50);
    assertCleanPage($page);
})->with(browserProjects());

it('deletes an order through the row menu after the confirm', function (string $project) {
    $page = indexRowsPage($project, '/lathe/resources/orders');
    $page->assertSee('Showing 1 to 25 of 10,000');
    $number = firstRowTitle($page);

    $page->click('[aria-label="Actions for '.$number.'"]')
        ->click('button[role="menuitem"]')
        ->assertSeeIn('[role="alertdialog"]', 'Delete '.$number.'?')
        ->click('[role="alertdialog"] div > button:last-child')
        ->assertSee($number.' was deleted.')
        ->assertDontSeeIn('table', $number)
        ->assertCount('table tbody > tr', 25);

    expect(Order::where('number', $number)->exists())->toBeFalse();
    assertCleanPage($page);
})->with(browserProjects());

it('restores a trashed post from the row menu', function (string $project) {
    $page = indexRowsPage($project, '/lathe/resources/posts?trashed=only');
    $page->assertSee('Soft Deleted')
        ->assertCount('table tbody > tr', 10);
    $title = firstRowTitle($page);

    $page->click('[aria-label="Actions for '.$title.'"]')
        ->click('Restore')
        ->assertSee($title.' was restored.')
        ->assertCount('table tbody > tr', 9)
        ->assertDontSeeIn('table', $title);

    expect(Post::where('title', $title)->first()?->trashed())->toBeFalse();
    assertCleanPage($page);
})->with(browserProjects());
