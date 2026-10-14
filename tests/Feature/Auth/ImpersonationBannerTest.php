<?php

use Inertia\Testing\AssertableInertia;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);

    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->editor = User::where('email', 'editor@lathephp.com')->firstOrFail();
});

function impersonateEditor(): void
{
    test()->actingAs(test()->admin);
    test()->postJson('/lathe/api/impersonate', ['resource' => 'users', 'resourceId' => test()->editor->id])->assertOk();
}

it('names the impersonated user on every shell page', function (string $url, int $status) {
    impersonateEditor();
    $url = str_replace(
        ['{order}', '{product}'],
        [(string) Order::query()->firstOrFail()->getKey(), (string) Product::query()->firstOrFail()->getKey()],
        $url,
    );

    $this->get($url)->assertStatus($status)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('auth.user.name', 'Editor User')
        ->where('impersonating', ['name' => 'Editor User']));
})->with([
    'dashboard' => ['/lathe', 200],
    'resource index' => ['/lathe/resources/orders', 200],
    'record detail' => ['/lathe/resources/orders/{order}', 200],
    // OrderPolicy keeps the editor out of the order form, so the form page is a product's.
    'record edit' => ['/lathe/resources/products/{product}/edit', 200],
    'plugin page' => ['/lathe/plugins/heartbeat', 200],
    'forbidden page' => ['/lathe/pages/system-status', 403],
]);

it('shares impersonating as null when no impersonation runs', function () {
    $this->actingAs($this->admin);

    $this->get('/lathe')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('impersonating', null));
});

it('answers the impersonated editor 403 on an order delete, as OrderPolicy decides for the editor', function () {
    impersonateEditor();

    $this->deleteJson('/lathe/api/resources/orders/1')->assertForbidden();
});

it('ends the whole session when the impersonated user logs out', function () {
    impersonateEditor();

    $this->post('/lathe/logout')->assertRedirect();

    foreach (['lathe_impersonated_by', 'lathe_impersonated_remember', 'lathe_impersonated_from', 'lathe_impersonated_guard'] as $key) {
        expect(session()->has($key))->toBeFalse();
    }

    $this->assertGuest();
});
