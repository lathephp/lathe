<?php

use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\Product;

require_once __DIR__.'/records.php';

const BILLING_TAB = '[role="tab"][aria-controls$="-billing"]';
const ITEMS_TAB = '[role="tab"][aria-controls$="-items"]';
const SPEC_ROWS = '#field-specifications li[data-repeater-row]';
const SPEC_TITLES = '[...document.querySelectorAll("#field-specifications [data-row-title]")].map((title) => title.textContent)';

it('shows the Order and Customer cards and keeps the selected tab in the URL', function (string $project) {
    $page = signedInAt('/lathe/resources/orders/12', $project);

    $page->assertPresent('section[aria-label="Order"]')
        ->assertPresent('section[aria-label="Customer"]')
        ->assertSeeIn(BILLING_TAB, 'Billing')
        ->click(ITEMS_TAB)
        ->assertQueryStringHas('tab', 'items')
        ->click(BILLING_TAB)
        ->assertQueryStringHas('tab', 'billing')
        ->assertAttribute(BILLING_TAB, 'aria-selected', 'true')
        ->click(ITEMS_TAB)
        ->refresh()
        ->assertAttribute(ITEMS_TAB, 'aria-selected', 'true')
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with(browserProjects());

it('marks the Billing tab with an error dot and opens it when its Total is empty', function () {
    $page = signedInAt('/lathe/resources/orders/12/edit');

    $page->clear('#field-total_cents')
        ->click(ITEMS_TAB)
        ->assertAttribute(BILLING_TAB, 'aria-selected', 'false')
        ->click('form button[type="submit"]')
        ->assertPresent(BILLING_TAB.' [aria-label="Has errors"]')
        ->assertAttribute(BILLING_TAB, 'aria-selected', 'true')
        ->assertVisible('#field-total_cents-error')
        ->assertPathIs('/lathe/resources/orders/12/edit');
});

it('shows Company name only for a company customer and saves none for a person', function () {
    $page = signedInAt('/lathe/resources/customers/new');

    $page->assertNotPresent('#field-company_name')
        ->select('#field-type', 'company')
        ->assertVisible('#field-company_name')
        ->type('#field-company_name', 'Acme')
        ->select('#field-type', 'person')
        ->assertNotPresent('#field-company_name')
        ->type('#field-name', 'Person Customer')
        ->type('#field-email', 'person@example.com')
        ->click('form button[type="submit"]')
        ->assertScript('/^\/lathe\/resources\/customers\/\d+$/.test(location.pathname)');

    expect(Customer::where('email', 'person@example.com')->sole()->company_name)->toBeNull();
    assertCleanPage($page);
});

it('changes the State options with the Country', function () {
    $page = signedInAt('/lathe/resources/customers/new');
    $states = '[...document.querySelectorAll("#field-state option")].map((option) => option.value).filter((value) => value !== "")';

    $page->assertSelected('#field-country', 'US')
        ->assertScript($states, ['California', 'New York', 'Texas'])
        ->select('#field-country', 'BD')
        ->assertScript($states, ['Chittagong', 'Dhaka', 'Khulna']);
    assertCleanPage($page);
});

it('adds, moves and removes specification rows, and Escape on the confirm keeps the row', function () {
    $product = Product::query()->firstOrFail();
    $product->forceFill(['specifications' => [['name' => 'Colour', 'value' => 'Red'], ['name' => 'Size', 'value' => 'M']]])->save();

    $page = signedInAt("/lathe/resources/products/{$product->id}/edit");

    $page->assertCount(SPEC_ROWS, 2)
        ->click('Add spec')
        ->assertCount(SPEC_ROWS, 3)
        ->type('#field-specifications-2-name', 'Weight')
        ->type('#field-specifications-2-value', '2 kg')
        // Drag the new row above the first one.
        ->drag(SPEC_ROWS.':nth-child(3) [aria-label="Drag to reorder"]', SPEC_ROWS.':nth-child(1)')
        ->assertScript(SPEC_TITLES, ['Weight', 'Colour', 'Size'])
        // The keyboard route: Move down on the first row.
        ->click(SPEC_ROWS.':nth-child(1) [aria-label="Move down"]')
        ->assertScript(SPEC_TITLES, ['Colour', 'Weight', 'Size'])
        ->click(SPEC_ROWS.':nth-child(3) [aria-label="Remove"]')
        ->assertSeeIn('[role="dialog"]', 'Remove this row?')
        ->keys('[role="dialog"]', 'Escape')
        ->assertNotPresent('[role="dialog"]')
        ->assertCount(SPEC_ROWS, 3)
        ->click(SPEC_ROWS.':nth-child(3) [aria-label="Remove"]')
        ->click('[role="dialog"] button:last-child')
        ->assertCount(SPEC_ROWS, 2)
        ->click('form button[type="submit"]')
        ->assertPathIs("/lathe/resources/products/{$product->id}");

    expect($product->fresh()?->specifications)->toBe([['name' => 'Colour', 'value' => 'Red'], ['name' => 'Weight', 'value' => '2 kg']]);
    assertCleanPage($page);
});

it('collapses and clones a specification row')
    ->todo(note: 'The workbench Products Specifications repeater is not collapsible() or cloneable(), so no Collapse or Clone button renders.');

it('removes one of three order items after the confirm and keeps the ids of the other two', function () {
    $ids = OrderItem::where('order_id', 12)->orderBy('id')->pluck('id')->all();
    expect($ids)->toHaveCount(3);

    $page = signedInAt('/lathe/resources/orders/12/edit?tab=items');

    $page->assertCount('#field-items li[data-repeater-row]', 3)
        ->click('#field-items li[data-repeater-row]:nth-child(2) [aria-label="Remove"]')
        ->assertSeeIn('[role="dialog"]', 'Remove this row?')
        ->click('[role="dialog"] button:last-child')
        ->assertCount('#field-items li[data-repeater-row]', 2)
        ->click('form button[type="submit"]')
        ->assertPathIs('/lathe/resources/orders/12');

    expect(Order::find(12)?->items()->orderBy('id')->pluck('id')->all())->toBe([$ids[0], $ids[2]]);
    assertCleanPage($page);
});
