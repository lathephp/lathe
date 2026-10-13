<?php

use Workbench\App\Models\Order;

require_once __DIR__.'/records.php';

const CONFLICT_DIALOG = '[role="dialog"]';

/**
 * Two people on order 12's edit form, each in their own browser context. The second saves
 * shipped two seconds later, so the first form's token is stale.
 *
 * @return array{0: Pest\Browser\Api\AwaitableWebpage, 1: Pest\Browser\Api\AwaitableWebpage}
 */
function staleOrderForm(): array
{
    Order::whereKey(12)->update(['status' => 'pending']);

    $first = signedInAt('/lathe/resources/orders/12/edit')->assertSelected('#field-status', 'pending');
    $second = signedInAt('/lathe/resources/orders/12/edit')->assertSelected('#field-status', 'pending');

    test()->travel(2)->seconds();

    $second->select('#field-status', 'shipped')
        ->click('form button[type="submit"]')
        ->assertPathIs('/lathe/resources/orders/12');

    expect(Order::find(12)?->status)->toBe('shipped');

    test()->travel(2)->seconds();

    $first->select('#field-status', 'cancelled')
        ->click('form button[type="submit"]')
        ->assertSeeIn(CONFLICT_DIALOG, 'This record has changed')
        ->assertPathIs('/lathe/resources/orders/12/edit');

    expect(Order::find(12)?->status)->toBe('shipped');

    return [$first, $second];
}

it('asks before overwriting a newer save, and Refresh loads the newer values', function () {
    [$first] = staleOrderForm();

    $first->click(CONFLICT_DIALOG.' button:first-of-type')
        ->assertNotPresent(CONFLICT_DIALOG)
        ->assertSelected('#field-status', 'shipped');

    // The refreshed form carries the new token, so the next save goes through.
    $first->select('#field-status', 'paid')
        ->click('form button[type="submit"]')
        ->assertPathIs('/lathe/resources/orders/12');

    expect(Order::find(12)?->status)->toBe('paid');
    assertCleanPage($first);
});

it('saves over the newer values with Override', function () {
    [$first] = staleOrderForm();

    $first->click(CONFLICT_DIALOG.' button:last-of-type')
        ->assertPathIs('/lathe/resources/orders/12');

    expect(Order::find(12)?->status)->toBe('cancelled');
    assertCleanPage($first);
});
