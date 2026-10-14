<?php

use Pest\Browser\Api\AwaitableWebpage;
use Workbench\App\Models\Order;

require_once __DIR__.'/records.php';

/** The names the open Customer picker lists, without their subtitles. */
const PICKER_NAMES = '[...document.querySelectorAll(\'[role="option"]\')].map((option) => option.firstChild?.textContent ?? "")';

function fillOrder(AwaitableWebpage $page, string $number): AwaitableWebpage
{
    return $page->type('#field-number', $number)
        ->select('#field-status', 'paid')
        ->type('#field-placed_at', '2026-10-01T10:30')
        ->type('#field-total_cents', '42.50');
}

it('creates an order with a customer picked after typing ann', function () {
    $page = fillOrder(signedInAt('/lathe/resources/orders/new'), 'ORD-PICK0001');

    $page->click('#field-customer')
        ->assertPresent('[role="listbox"] [role="option"]')
        ->type('input[aria-controls="field-customer-listbox"]', 'ann')
        // The picker waits its 500 ms debounce before it asks again.
        ->wait(1.5)
        ->assertScript('() => '.PICKER_NAMES.'.every((name) => name.startsWith("Ann"))');

    $names = $page->script(PICKER_NAMES);

    expect($names)->not->toBeEmpty()
        ->and(count($names))->toBeLessThanOrEqual(25);

    $page->click('[role="option"]:first-child')
        ->assertSeeIn('#field-customer', $names[0])
        ->click('form button[type="submit"]')
        ->assertScript('/^\/lathe\/resources\/orders\/\d+$/.test(location.pathname)')
        ->assertSee($names[0]);

    expect(Order::where('number', 'ORD-PICK0001')->sole()->customer?->name)->toBe($names[0]);
    assertCleanPage($page);
});

it('refuses an order without a customer and shows the message under Customer', function () {
    $count = Order::count();
    $page = fillOrder(signedInAt('/lathe/resources/orders/new'), 'ORD-PICK0002');

    $page->click('form button[type="submit"]')
        ->assertPresent('#field-customer-error')
        ->assertAttribute('#field-customer', 'aria-invalid', 'true')
        ->assertPathIs('/lathe/resources/orders/new');

    expect(Order::count())->toBe($count);
});
