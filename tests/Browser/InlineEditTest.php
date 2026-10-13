<?php

use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;

require_once __DIR__.'/records.php';

const FIRST_ROW = 'table tbody tr:first-child';
const NAME_CELL = FIRST_ROW.' [data-inline-cell]';

it('saves an Orders status chosen in its cell and keeps it after a reload', function (string $project) {
    $order = Order::query()->latest('id')->firstOrFail();
    $order->update(['status' => 'pending']);

    $page = signedInAt('/lathe/resources/orders', $project);

    $page->assertSee('Showing 1 to 25')
        ->assertSelected(FIRST_ROW.' select[aria-label="Status"]', 'pending')
        ->select(FIRST_ROW.' select[aria-label="Status"]', 'shipped')
        ->assertScript(requestCount("/orders/{$order->id}/field/status"), 1);

    expect($order->fresh()?->status)->toBe('shipped');

    $page->refresh()
        ->assertSelected(FIRST_ROW.' select[aria-label="Status"]', 'shipped');
    assertCleanPage($page);
})->with(browserProjects());

it('saves a Customers name typed in its cell on Enter with one request', function () {
    $customer = Customer::query()->latest('id')->firstOrFail();
    $page = signedInAt('/lathe/resources/customers');

    $page->assertSeeIn(NAME_CELL, $customer->name)
        ->click(NAME_CELL.' button')
        ->fill(NAME_CELL.' input', 'Ann Lee')
        ->keys(NAME_CELL.' input', 'Enter')
        ->assertNotPresent(NAME_CELL.' input')
        ->assertSeeIn(NAME_CELL, 'Ann Lee')
        ->assertScript(requestCount("/customers/{$customer->id}/field/name"), 1);

    expect($customer->fresh()?->name)->toBe('Ann Lee');
    assertCleanPage($page);
});

it('shows the rule message for a cleared name and brings the old name back', function () {
    $customer = Customer::query()->latest('id')->firstOrFail();
    $page = signedInAt('/lathe/resources/customers');

    $page->click(NAME_CELL.' button')
        ->clear(NAME_CELL.' input')
        ->keys(NAME_CELL.' input', 'Enter')
        ->assertSeeIn(NAME_CELL.' [role="tooltip"]', 'The name field is required.')
        ->assertSeeIn(NAME_CELL.' button', $customer->name);

    expect($customer->fresh()?->name)->toBe($customer->name);
    assertCleanPage($page);
});

it('reverts a cell on Escape without a request', function () {
    $customer = Customer::query()->latest('id')->firstOrFail();
    $page = signedInAt('/lathe/resources/customers');

    $page->click(NAME_CELL.' button')
        ->fill(NAME_CELL.' input', 'Never Saved')
        ->keys(NAME_CELL.' input', 'Escape')
        ->assertNotPresent(NAME_CELL.' input')
        ->assertSeeIn(NAME_CELL, $customer->name);

    expect($customer->fresh()?->name)->toBe($customer->name)
        ->and($page->script(requestCount('/field/name')))->toBe(0);
    assertCleanPage($page);
});
