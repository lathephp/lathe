<?php

use Pest\Browser\Api\AwaitableWebpage;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;

require_once __DIR__.'/records.php';

function fillInlineOrder(AwaitableWebpage $page, string $number): AwaitableWebpage
{
    return $page->type('#field-number', $number)
        ->select('#field-status', 'paid')
        ->type('#field-placed_at', '2026-10-01T10:30')
        ->type('#field-total_cents', '42.50');
}

it('creates a customer from the order form and orders for them', function () {
    $page = fillInlineOrder(signedInAt('/lathe/resources/orders/new'), 'ORD-INLINE01');

    $page->click('button[aria-label="Create Customer"]')
        ->assertPresent('[role="dialog"]')
        ->type('[role="dialog"] #inline-customers-name', 'Zed Inline')
        ->type('[role="dialog"] #inline-customers-email', 'zed.inline@example.com')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertMissing('[role="dialog"]')
        ->assertSeeIn('#field-customer', 'Zed Inline')
        // The save left the order form where it was.
        ->assertPathIs('/lathe/resources/orders/new');

    expect(Customer::where('email', 'zed.inline@example.com')->sole()->name)->toBe('Zed Inline');

    $page->click('form button[type="submit"]')
        ->assertScript('/^\/lathe\/resources\/orders\/\d+$/.test(location.pathname)')
        ->assertSee('Zed Inline');

    expect(Order::where('number', 'ORD-INLINE01')->sole()->customer?->name)->toBe('Zed Inline');
    assertCleanPage($page);
});
