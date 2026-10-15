<?php

use Workbench\App\Models\Order;
use Workbench\App\Models\Tag;

require_once __DIR__.'/records.php';
require_once __DIR__.'/queues.php';

const ACTIONS_BUTTON = 'button[aria-haspopup="menu"]:text-is("Actions")';

it('selects every pending order with mod+a then mod+shift+a and ships them in the queue', function () {
    onFileDatabase();
    $pending = Order::where('status', 'pending')->pluck('id');
    $badge = number_format($pending->count());

    $page = signedInAt('/lathe/resources/orders?tab=pending');

    $page->assertSeeIn('[data-tab="pending"]', $badge)
        ->assertSee('Showing 1 to 25 of '.$badge)
        ->keys('#app', modKey($page, 'a'))
        ->assertSee('All 25 rows on this page are selected.')
        ->assertSee("Select all matching rows ({$badge})")
        ->keys('#app', modKey($page, 'Shift+a'))
        ->assertSee("All matching rows selected ({$badge})")
        ->click(ACTIONS_BUTTON)
        ->click('[role="menuitem"]:has-text("Mark Shipped")')
        ->assertSeeIn('[role="dialog"] h2', 'Mark Shipped')
        ->click('Run Action')
        ->assertPresent('section[aria-label="Runs"] [role="status"]')
        ->assertDontSeeIn('section[aria-label="Runs"]', 'Action was executed successfully.');

    drainQueue();

    $page->assertSeeIn('section[aria-label="Runs"]', 'Action was executed successfully.');

    expect(Order::whereIn('id', $pending)->where('status', '!=', 'shipped')->count())->toBe(0);

    $page->navigate('/lathe/resources/orders?q='.Order::findOrFail($pending->first())->number)
        ->assertCount('table tbody > tr', 1)
        ->assertValue('table tbody > tr select', 'shipped');
    assertCleanPage($page);
});

/** Open the order list on one order and tick its row. */
function tickedOrder(Order $order)
{
    return signedInAt('/lathe/resources/orders?q='.$order->number)
        ->assertCount('table tbody > tr', 1)
        ->click('[aria-label="Select '.$order->number.'"]');
}

it('cancels a ticked order after the red Cancel 1 order? confirm', function () {
    $order = Order::where('status', 'paid')->firstOrFail();
    $page = tickedOrder($order);

    $page->click(ACTIONS_BUTTON)
        ->click('[role="menuitem"]:has-text("Cancel Order")')
        ->assertSeeIn('[role="dialog"]', 'Cancel 1 order?')
        ->assertAttributeContains('[role="dialog"] button[type="submit"]', 'class', 'bg-destructive')
        ->click('Run Action')
        ->assertSee('Action was executed successfully.')
        ->assertValue('table tbody > tr select', 'cancelled');

    expect($order->fresh()?->status)->toBe('cancelled');
    assertCleanPage($page);
});

it('opens the Heartbeat hold-order modal for order 12', function () {
    $page = tickedOrder(Order::findOrFail(12));

    $page->click(ACTIONS_BUTTON)
        ->click('[role="menuitem"]:has-text("Hold Order")')
        ->assertSeeIn('[role="dialog"]', 'Order #12 is on hold.');
    assertCleanPage($page);
});

it('merges two tags into the one picked in the modal', function () {
    $page = signedInAt('/lathe/resources/tags')->assertPresent('table tbody > tr');
    $sources = $page->script("[...document.querySelectorAll('table tbody > tr input[aria-label^=\"Select \"]')].slice(0, 2).map((box) => box.getAttribute('aria-label').slice(7))");
    $sale = Tag::where('name', 'sale')->sole();
    $products = Tag::whereIn('name', $sources)->with('products')->get()->flatMap->products->pluck('id')->unique()->sort()->values()->all();

    expect($sources)->toHaveCount(2)->and($products)->not->toBeEmpty();

    foreach ($sources as $name) {
        $page->click('[aria-label="Select '.$name.'"]');
    }

    $page->click(ACTIONS_BUTTON)
        ->click('[role="menuitem"]:has-text("Merge Tags")')
        // The select turns into the searchable picker once its first page shows there are more than 25 tags.
        ->click('#action-merge-tags-target')
        ->click('button#action-merge-tags-target')
        ->type('input[aria-controls="action-merge-tags-target-listbox"]', 'sale')
        ->click('[role="option"]:has-text("sale")')
        ->click('Run Action')
        ->assertSee('Tags merged.')
        ->assertDontSeeIn('table tbody', $sources[0]);

    expect(Tag::whereIn('name', $sources)->count())->toBe(0)
        ->and($sale->products()->pluck('products.id')->intersect($products)->sort()->values()->all())->toBe($products);
    assertCleanPage($page);
});

it('keeps the Actions menu and the action modal free of serious axe issues and pauses shortcuts', function () {
    $page = tickedOrder(Order::findOrFail(12));

    $page->click(ACTIONS_BUTTON)
        ->assertPresent('[role="menu"][aria-label="Actions"]')
        ->assertNoAccessibilityIssues()
        ->click('[role="menuitem"]:has-text("Mark Shipped")')
        ->assertSeeIn('[role="dialog"]', 'Mark 1 orders as shipped?')
        ->assertNoAccessibilityIssues()
        ->assertScript("import('@lathephp/core').then((core) => core.__panel.shortcutPauseCount())", 1)
        ->keys('[role="dialog"]', 'c')
        ->wait(0.5)
        ->assertPathIs('/lathe/resources/orders');
    assertCleanPage($page);
});
