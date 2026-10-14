<?php

use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;

require_once __DIR__.'/records.php';

const PANEL_ITEMS_TAB = '[role="tab"][aria-controls$="-items"]';
const ITEMS_PANEL = 'section[aria-label="Items"]';
const ITEM_ROWS = 'section[aria-label="Items"] tbody tr';
const ITEM_QUANTITIES = '[...document.querySelectorAll(\'section[aria-label="Items"] tbody tr td:last-child\')].map((cell) => cell.textContent.trim())';
// Inertia visits ask for the page path itself; the panel only asks the API.
const PAGE_VISITS = 'performance.getEntriesByType("resource").filter((entry) => new URL(entry.name).pathname === "/lathe/resources/orders/12").length';

it('lists the items of an order in its Items tab and sorts them without a visit', function () {
    $quantities = Order::findOrFail(12)->items()->orderBy('quantity')->pluck('quantity')->map(fn ($quantity) => (string) $quantity)->all();

    $page = signedInAt('/lathe/resources/orders/12');

    $page->assertScript(requestCount('/orders/12/items'), 0)
        ->click(PANEL_ITEMS_TAB)
        ->assertSeeIn(ITEMS_PANEL, 'Product')
        ->assertSeeIn(ITEMS_PANEL, 'Quantity')
        ->assertCount(ITEM_ROWS, count($quantities))
        ->assertScript(requestCount('/orders/12/items'), 1);

    $page->script('window.__unloaded = false');
    $page->click(ITEMS_PANEL.' th button')
        ->assertScript(requestCount('/orders/12/items'), 2)
        ->assertScript(ITEM_QUANTITIES, $quantities)
        ->assertScript('window.__unloaded', false)
        ->assertScript(PAGE_VISITS, 0);
    assertCleanPage($page);
});

it('creates an item from the Items panel with the order locked', function () {
    $before = Order::findOrFail(12)->items()->count();
    $product = Product::query()->firstOrFail();

    $page = signedInAt('/lathe/resources/orders/12?tab=items');

    $page->assertCount(ITEM_ROWS, $before)
        ->click(ITEMS_PANEL.' a[href$="/new?via=orders/12/items"]')
        ->assertPathIs('/lathe/resources/order-items/new')
        ->assertSeeIn('#field-order', Order::findOrFail(12)->number)
        ->assertNotPresent('button#field-order')
        ->click('#field-product')
        ->type('input[aria-controls="field-product-listbox"]', $product->name)
        // The picker waits its 500 ms debounce before it asks again.
        ->wait(1.5)
        ->click('[role="option"]:first-child')
        ->type('#field-quantity', '2')
        ->type('#field-price_cents', '9.99')
        ->click('form button[type="submit"]')
        ->assertPathIs('/lathe/resources/orders/12')
        ->click(PANEL_ITEMS_TAB)
        ->assertCount(ITEM_ROWS, $before + 1);

    expect(Order::findOrFail(12)->items()->where('product_id', $product->id)->where('quantity', 2)->exists())->toBeTrue();
    assertCleanPage($page);
});

it('creates an order from a customer with the customer locked, for an admin only', function () {
    $page = signedInAt('/lathe/resources/customers/12');

    $page->click('section[aria-label="Orders"] a[href$="/new?via=customers/12/orders"]')
        ->assertPathIs('/lathe/resources/orders/new')
        ->assertSeeIn('#field-customer', Customer::findOrFail(12)->name)
        ->assertNotPresent('button#field-customer');
    assertCleanPage($page);

    $editor = signedInAt('/lathe/resources/customers/12', email: 'editor@lathephp.com');

    $editor->assertPresent('section[aria-label="Orders"] tbody tr')
        ->assertNotPresent('section[aria-label="Orders"] a[href*="/new?via="]');
    assertCleanPage($editor);
});
