<?php

use Workbench\App\Models\Order;

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
        ->keys('#app', 'Control+a')
        ->assertSee('All 25 rows on this page are selected.')
        ->assertSee("Select all matching rows ({$badge})")
        ->keys('#app', 'Control+Shift+a')
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
