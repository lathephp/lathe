<?php

use Illuminate\Support\Facades\DB;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;

require_once __DIR__.'/records.php';

/** An order made in the test, so deleting it touches no seeded row. */
function freshOrder(): Order
{
    $id = DB::table('orders')->insertGetId([
        'customer_id' => 1,
        'number' => 'ORD-DEL00001',
        'status' => 'paid',
        'total_cents' => 1000,
        'placed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Order::findOrFail($id);
}

it('opens the replicate form of order 12 from the row menu with the number empty', function () {
    $order = Order::findOrFail(12);
    $count = Order::count();
    $page = signedInAt('/lathe/resources/orders?q='.$order->number);

    $page->assertCount('table tbody > tr', 1)
        ->click('[aria-label="Actions for '.$order->number.'"]')
        ->click('[role="menuitem"][href$="/orders/12/replicate"]')
        ->assertPathIs('/lathe/resources/orders/12/replicate')
        ->assertValue('#field-number', '')
        ->assertSelected('#field-status', $order->status)
        ->assertValue('#field-total_cents', number_format($order->total_cents / 100, 2, '.', ''));

    expect(Order::count())->toBe($count);
    assertCleanPage($page);
});

it('saves a replicated customer with a new email and opens the new record', function () {
    $source = Customer::query()->latest('id')->firstOrFail();
    $page = signedInAt('/lathe/resources/customers');

    $page->click('[aria-label="Actions for '.$source->name.'"]')
        ->click('[role="menuitem"][href$="/customers/'.$source->id.'/replicate"]')
        ->assertPathIs("/lathe/resources/customers/{$source->id}/replicate")
        ->assertValue('#field-name', $source->name)
        ->clear('#field-email')
        ->type('#field-email', 'replica@example.com')
        ->click('form button[type="submit"]')
        ->assertScript('/^\/lathe\/resources\/customers\/\d+$/.test(location.pathname)')
        ->assertPathIsNot("/lathe/resources/customers/{$source->id}");

    $replica = Customer::where('email', 'replica@example.com')->sole();

    expect($page->script('location.pathname'))->toBe("/lathe/resources/customers/{$replica->id}")
        ->and($replica->name)->toBe($source->name)
        ->and($source->fresh()?->email)->toBe($source->email);
    assertCleanPage($page);
});

it('deletes an order from its detail page after a destructive confirm', function () {
    $order = freshOrder();
    $page = signedInAt("/lathe/resources/orders/{$order->id}");

    $page->click('main header button.text-destructive')
        ->assertSeeIn('[role="dialog"]', "Delete {$order->number}?")
        ->assertAttributeContains('[role="dialog"] button:last-child', 'class', 'bg-destructive')
        ->click('[role="dialog"] button:last-child')
        ->assertPathIs('/lathe/resources/orders')
        ->assertSee("{$order->number} was deleted.");

    expect(Order::find($order->id))->toBeNull();
    assertCleanPage($page);
});

it('shows the editor no Delete button on an order', function () {
    $order = freshOrder();
    $page = signedInAt("/lathe/resources/orders/{$order->id}", email: 'editor@lathephp.com');

    $page->assertScript("document.querySelector('main h1')?.textContent", $order->number)
        ->assertScript("[...document.querySelectorAll('main header button')].map((button) => button.textContent)", []);
    assertCleanPage($page);
});

it('shows a trashed post with its fields, the Soft Deleted banner, Restore and Force Delete', function (string $project) {
    $post = Post::onlyTrashed()->firstOrFail();
    $page = signedInAt("/lathe/resources/posts/{$post->id}", $project);

    $page->assertScript("document.querySelector('main h1')?.textContent", $post->title)
        ->assertSeeIn('[role="status"]', 'Soft Deleted')
        ->assertSeeIn('section[aria-label="Details"]', 'Title')
        ->assertScript("document.querySelector('section[aria-label=\"Details\"]').textContent.includes(".json_encode($post->title).')')
        ->assertScript("[...document.querySelectorAll('main header button')].map((button) => button.textContent)", ['Restore', 'Force Delete'])
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
})->with(browserProjects());

it('restores a trashed post from its detail page', function () {
    $post = Post::onlyTrashed()->firstOrFail();
    $page = signedInAt("/lathe/resources/posts/{$post->id}");

    $page->assertSeeIn('[role="status"]', 'Soft Deleted')
        ->click('Restore')
        ->assertSee("{$post->title} was restored.")
        ->assertNotPresent('[role="status"]')
        ->assertPathIs("/lathe/resources/posts/{$post->id}");

    expect($post->fresh()?->trashed())->toBeFalse();
    assertCleanPage($page);
});

it('force deletes a trashed post after the confirm, and its old address answers 404', function () {
    $post = Post::onlyTrashed()->firstOrFail();
    $page = signedInAt("/lathe/resources/posts/{$post->id}");

    $page->click('main header button.text-destructive')
        ->assertSeeIn('[role="dialog"]', "Delete {$post->title} for good?")
        ->click('[role="dialog"] button:last-child')
        ->assertPathIs('/lathe/resources/posts')
        ->assertSee("{$post->title} was deleted for good.");

    expect(Post::withTrashed()->find($post->id))->toBeNull();

    $page->navigate("/lathe/resources/posts/{$post->id}")
        ->assertSee('Page not found');
});

it('previews order 12 from its row in a modal without leaving the list', function () {
    $order = Order::findOrFail(12);
    $page = signedInAt('/lathe/resources/orders?q='.$order->number);

    $page->click('[aria-label="Preview '.$order->number.'"]')
        ->assertVisible('[role="dialog"]')
        ->assertSeeIn('[role="dialog"] h2', $order->number)
        ->assertSeeIn('[role="dialog"] dl', 'Placed')
        ->assertSeeIn('[role="dialog"] dl', 'Total')
        ->assertPathIs('/lathe/resources/orders');

    assertCleanPage($page);
});
