<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\AwaitableWebpage;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;

require_once __DIR__.'/records.php';

const SUBMIT = 'form button[type="submit"]';
const DETAIL_PATH = '/^\/lathe\/resources\/%s\/\d+$/.test(location.pathname)';

function detailPathOf(string $uriKey): string
{
    return sprintf(DETAIL_PATH, $uriKey);
}

/**
 * End the browser's session the way an expired cookie does: the next request carries no
 * session and the in-process server forgets the user it held.
 */
function expireSession(AwaitableWebpage $page): void
{
    $context = (fn () => $this->page)->call($page)->context();

    foreach ((fn () => $this->sendMessage('clearCookies', ['name' => config('session.cookie')]))->call($context) as $message) {
        //
    }

    forgetServerSession();
}

it('stays on the create form with Create & Add Another and clears it to the defaults', function () {
    $page = signedInAt('/lathe/resources/customers/new');

    $page->type('#field-name', 'Ann Another')
        ->type('#field-email', 'ann.another@example.com')
        ->select('#field-country', 'BD')
        ->click('Create & Add Another')
        ->assertSee('Customer created.')
        ->assertPathIs('/lathe/resources/customers/new')
        ->assertValue('#field-name', '')
        ->assertValue('#field-email', '')
        ->assertSelected('#field-country', 'US');

    expect(Customer::where('email', 'ann.another@example.com')->sole()->country)->toBe('BD');
    assertCleanPage($page);
});

it('stays on the edit form with Update & Continue Editing and saves again with the new token', function () {
    $customer = Customer::query()->firstOrFail();
    $page = signedInAt("/lathe/resources/customers/{$customer->id}/edit");

    $page->clear('#field-name')
        ->type('#field-name', 'First Rename')
        ->click('Update & Continue Editing')
        ->assertSee('Customer updated.')
        ->assertPathIs("/lathe/resources/customers/{$customer->id}/edit")
        ->assertValue('#field-name', 'First Rename');

    expect($customer->fresh()?->name)->toBe('First Rename');

    // The fresh form holds the new updatedAt, so a later save meets no conflict.
    $this->travel(2)->seconds();

    $page->clear('#field-name')
        ->type('#field-name', 'Second Rename')
        ->click(SUBMIT)
        ->assertPathIs("/lathe/resources/customers/{$customer->id}")
        ->assertNotPresent('[role="dialog"]');

    expect($customer->fresh()?->name)->toBe('Second Rename');
    assertCleanPage($page);
});

it('opens the new record after a plain create', function () {
    $page = signedInAt('/lathe/resources/customers/new');

    $page->type('#field-name', 'Plain Create')
        ->type('#field-email', 'plain.create@example.com')
        ->click(SUBMIT)
        ->assertScript(detailPathOf('customers'));

    expect($page->script('location.pathname'))->toBe('/lathe/resources/customers/'.Customer::where('email', 'plain.create@example.com')->sole()->id);
    assertCleanPage($page);
});

it('shows the rule message under an empty Name, keeps the typed values and focuses Name', function (string $project) {
    $count = Customer::count();
    $page = signedInAt('/lathe/resources/customers/new', $project);

    $page->type('#field-email', 'no.name@example.com')
        ->select('#field-country', 'BD')
        ->click(SUBMIT)
        ->assertSeeIn('#field-name-error', 'The name field is required.')
        ->assertSee('Please fix the highlighted fields.')
        ->assertAttribute('#field-name', 'aria-invalid', 'true')
        ->assertScript('document.activeElement?.id', 'field-name')
        ->assertValue('#field-email', 'no.name@example.com')
        ->assertSelected('#field-country', 'BD')
        ->assertPathIs('/lathe/resources/customers/new');

    expect(Customer::count())->toBe($count);
    assertCleanPage($page);
})->with(browserProjects());

it('signs in again over the form when the session expired and saves the typed values once', function () {
    $count = Customer::count();
    $page = signedInAt('/lathe/resources/customers/new');

    $page->type('#field-name', 'After Expiry')
        ->type('#field-email', 'after.expiry@example.com');

    expireSession($page);

    $page->click(SUBMIT)
        ->assertSeeIn('[role="dialog"]', 'Your session has expired')
        ->assertValue('[role="dialog"] input[type="email"]', 'admin@lathephp.com')
        ->type('[role="dialog"] input[type="password"]', 'password')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertScript(detailPathOf('customers'))
        ->assertNotPresent('[role="dialog"]');

    expect(Customer::count())->toBe($count + 1)
        ->and(Customer::where('email', 'after.expiry@example.com')->sole()->name)->toBe('After Expiry');
});

// The editor may create customers but not orders (OrderPolicy::create is the admin's).
it('opens the create form with c on Customers, and c does nothing on Orders without create', function () {
    $page = signedInAt('/lathe/resources/customers', email: 'editor@lathephp.com');

    $page->assertAttribute('a[href$="/resources/customers/new"]', 'aria-keyshortcuts', 'c')
        ->keys('#app', 'c')
        ->assertPathIs('/lathe/resources/customers/new')
        ->navigate('/lathe/resources/orders')
        ->assertSee('Showing 1 to 25')
        ->keys('#app', 'c')
        ->wait(0.5)
        ->assertPathIs('/lathe/resources/orders');
    assertCleanPage($page);
});

it('opens the edit form with e on order 12 for the admin', function () {
    $page = signedInAt('/lathe/resources/orders/12');

    $page->assertAttribute('a[href$="/resources/orders/12/edit"]', 'aria-keyshortcuts', 'e')
        ->keys('#app', 'e')
        ->assertPathIs('/lathe/resources/orders/12/edit');
    assertCleanPage($page);
});

it('keeps the editor on order 12 when e is pressed', function () {
    $page = signedInAt('/lathe/resources/orders/12', email: 'editor@lathephp.com');

    $page->assertScript("document.querySelector('main h1')?.textContent", Order::find(12)?->number)
        ->assertNotPresent('a[href$="/resources/orders/12/edit"]')
        ->keys('#app', 'e')
        ->wait(0.5)
        ->assertPathIs('/lathe/resources/orders/12');
    assertCleanPage($page);
});

it('shows the Create button on Customers and none on Orders without create', function (string $project) {
    $page = signedInAt('/lathe/resources/customers', $project, 'editor@lathephp.com');

    $page->assertSeeIn('a[href="/lathe/resources/customers/new"]', 'Create Customer')
        ->navigate('/lathe/resources/orders')
        ->assertSee('Showing 1 to 25')
        ->assertNotPresent('a[href$="/resources/orders/new"]');
    assertCleanPage($page);
})->with(browserProjects());

it('shows the empty state with the Create button on Customers and without it on Orders', function () {
    DB::table('order_items')->delete();
    DB::table('orders')->delete();
    DB::table('customers')->delete();

    $page = signedInAt('/lathe/resources/customers', email: 'editor@lathephp.com');

    $page->assertSee('No customers yet')
        ->assertCount('a[href="/lathe/resources/customers/new"]', 2)
        ->navigate('/lathe/resources/orders')
        ->assertSee('No orders yet')
        ->assertNotPresent('a[href$="/resources/orders/new"]')
        ->assertNoAccessibilityIssues();
    assertCleanPage($page);
});

it('lists the editor with the avatar image and the admin with the initials in the palette', function () {
    servePublicDisk();
    Storage::disk('public')->put('avatars/editor.png', (string) file_get_contents(uploadFixture('avatar-3mb.png')));
    User::where('email', 'editor@lathephp.com')->update(['avatar' => 'avatars/editor.png']);

    $page = signedInAt('/lathe');

    $page->keys('#app', 'Control+k')
        ->type('[role="combobox"]', 'editor')
        ->assertPresent('[role="group"][aria-label="Users"]')
        ->assertSeeIn('[role="group"][aria-label="Users"] [role="option"]', 'Editor User')
        ->assertScript('[...document.querySelectorAll(\'[role="group"][aria-label="Users"] [role="option"] img\')].some((img) => img.complete && img.naturalWidth === 1024)')
        ->clear('[role="combobox"]')
        ->type('[role="combobox"]', 'admin')
        ->assertSeeIn('[role="group"][aria-label="Users"] [role="option"]', 'Admin User')
        ->assertSeeIn('[role="group"][aria-label="Users"] [role="option"]', 'AU');
    assertCleanPage($page);
});

it('opens Customers create with United States and lists BD after picking Bangladesh', function () {
    $page = signedInAt('/lathe/resources/customers/new');

    $page->assertSelected('#field-country', 'US')
        ->assertScript("document.querySelector('#field-country').selectedOptions[0]?.textContent", 'United States')
        ->select('#field-country', 'BD')
        ->type('#field-name', 'Dhaka Customer')
        ->type('#field-email', 'dhaka@example.com')
        ->click(SUBMIT)
        ->assertScript(detailPathOf('customers'))
        ->navigate('/lathe/resources/customers')
        ->assertSeeIn('table tbody tr:first-child', 'Dhaka Customer')
        ->assertSeeIn('table tbody tr:first-child', 'BD');

    expect(Customer::where('email', 'dhaka@example.com')->sole()->country)->toBe('BD');
    assertCleanPage($page);
});

it('rejects a reorder level of 11 and saves 10', function () {
    $product = Product::query()->firstOrFail();
    $page = signedInAt("/lathe/resources/products/{$product->id}/edit");

    $page->assertAttribute('#field-reorder_level', 'max', '10')
        ->clear('#field-reorder_level')
        ->type('#field-reorder_level', '11')
        ->click(SUBMIT)
        ->assertSeeIn('#field-reorder_level-error', 'The reorder level field must not be greater than 10.')
        ->clear('#field-reorder_level')
        ->type('#field-reorder_level', '10')
        ->click(SUBMIT)
        ->assertPathIs("/lathe/resources/products/{$product->id}");

    expect($product->fresh()?->getAttribute('reorder_level'))->toBe(10);
    assertCleanPage($page);
});

it('saves a price of 12.5 and lists it as $12.50, with the SKU locked', function () {
    $product = Product::query()->latest('id')->firstOrFail();
    $page = signedInAt("/lathe/resources/products/{$product->id}/edit");

    $page->assertDisabled('#field-sku')
        ->assertValue('#field-sku', $product->sku)
        ->clear('#field-price_cents')
        ->type('#field-price_cents', '12.5')
        ->click(SUBMIT)
        ->assertPathIs("/lathe/resources/products/{$product->id}")
        ->navigate('/lathe/resources/products')
        ->assertSeeIn('table tbody tr:first-child', '$12.50')
        ->assertSeeIn('table tbody tr:first-child', $product->sku);

    expect($product->fresh()?->price_cents)->toBe(1250)
        ->and($product->fresh()?->sku)->toBe($product->sku);
    assertCleanPage($page);
});

it('fills the slug from the title until the slug is typed in', function () {
    $page = signedInAt('/lathe/resources/posts/new');

    $page->type('#field-title', 'Hello World')
        ->assertValue('#field-slug', 'hello-world')
        ->clear('#field-slug')
        ->type('#field-slug', 'hello-world')
        ->type('#field-title', 'Hello World Again')
        ->assertValue('#field-slug', 'hello-world')
        ->fill('#field-body', 'The body.')
        ->click(SUBMIT)
        ->assertScript(detailPathOf('posts'))
        ->assertSee('hello-world');

    // The editors' strict CSP check lives in A11yAndCspTest.
    expect(Post::where('title', 'Hello World Again')->sole()->slug)->toBe('hello-world');
});
