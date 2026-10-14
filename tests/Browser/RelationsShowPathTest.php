<?php

use Workbench\App\Models\Order;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag;

require_once __DIR__.'/records.php';

// The relations Show path: create an order, pick its customer by typing in the picker, add an item on
// its Items tab, then attach two tags to a product with a position.
it('walks the relations Show path from an order to a product\'s tags', function () {
    $dialog = '[role="dialog"]';
    $tagsPanel = 'section[aria-label="Tags"]';
    $product = Product::query()->orderBy('id')->firstOrFail();
    $attached = Product::findOrFail(1)->tags()->pluck('tags.id');
    $second = Tag::query()->where('name', '!=', 'sale')->whereNotIn('id', $attached)->orderBy('id')->firstOrFail()->name;

    // 1. Orders -> Create, the Customer picked by typing "ann".
    $page = signedInAt('/lathe/resources/orders/new');
    $page->type('#field-number', 'ORD-SHOWPATH')
        ->select('#field-status', 'paid')
        ->type('#field-placed_at', '2026-10-01T10:30')
        ->type('#field-total_cents', '42.50')
        ->click('#field-customer')
        ->type('input[aria-controls="field-customer-listbox"]', 'ann')
        // The picker waits its 500 ms debounce before it asks again.
        ->wait(1.5)
        ->assertScript('() => [...document.querySelectorAll(\'[role="option"]\')].every((option) => (option.firstChild?.textContent ?? "").startsWith("Ann"))')
        ->click('[role="option"]:first-child')
        ->click('form button[type="submit"]')
        ->assertScript('/^\/lathe\/resources\/orders\/\d+$/.test(location.pathname)');

    $order = Order::where('number', 'ORD-SHOWPATH')->sole();
    expect($order->customer?->name)->toStartWith('Ann');

    // 2. Its Items tab -> Create, the order locked, a product and quantity 2.
    $page->navigate("/lathe/resources/orders/{$order->id}?tab=items")
        ->click('section[aria-label="Items"] a[href$="/new?via=orders/'.$order->id.'/items"]')
        ->assertPathIs('/lathe/resources/order-items/new')
        ->assertSeeIn('#field-order', $order->number)
        ->click('#field-product')
        ->type('input[aria-controls="field-product-listbox"]', $product->name)
        ->wait(1.5)
        ->click('[role="option"]:first-child')
        ->type('#field-quantity', '2')
        ->type('#field-price_cents', '9.99')
        ->click('form button[type="submit"]')
        ->assertPathIs("/lathe/resources/orders/{$order->id}")
        ->click('[role="tab"][aria-controls$="-items"]')
        ->assertCount('section[aria-label="Items"] tbody tr', 1);

    expect($order->items()->where('quantity', 2)->count())->toBe(1);

    // 3. A product's Tags panel -> Attach "sale" at position 1, then a second tag at 2.
    $page->navigate('/lathe/resources/products/1')
        ->click($tagsPanel.' button:text-is("Attach")');

    foreach ([['sale', '1', 'Attach & attach another'], [$second, '2', 'Attach']] as [$name, $position, $button]) {
        $page->click($dialog.' button[aria-haspopup="listbox"]')
            ->type($dialog.' input[type="search"]', $name)
            ->wait(1.5)
            ->click($dialog.' [role="option"]:text-is("'.$name.'")')
            ->type($dialog.' input[name="position"]', $position)
            ->click($dialog.' button:text-is("'.$button.'")');
    }

    $page->assertNotPresent($dialog)
        ->assertSeeIn($tagsPanel, 'sale')
        ->assertSeeIn($tagsPanel, $second);

    $tags = Product::findOrFail(1)->tags()->get();
    expect((int) $tags->firstWhere('name', 'sale')?->pivot->position)->toBe(1)
        ->and((int) $tags->firstWhere('name', $second)?->pivot->position)->toBe(2);
    assertCleanPage($page);
});
