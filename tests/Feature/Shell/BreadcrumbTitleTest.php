<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\OrderItem as OrderItemModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('names the record by its title on the detail and edit breadcrumbs', function (string $url, int $count) {
    $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('breadcrumbs.2.label', 'ORD-00099454')
        ->where('breadcrumbs', fn ($crumbs) => collect($crumbs)->count() === $count
            && collect($crumbs)->every(fn ($crumb) => ! str_starts_with($crumb['label'], '#'))));
})->with([
    'detail' => ['/lathe/resources/orders/12', 3],
    'edit' => ['/lathe/resources/orders/12/edit', 4],
]);

it('names the parent record by its title on a child breadcrumb', function () {
    $item = OrderItemModel::query()->forceCreate(['order_id' => 12, 'product_id' => 1, 'quantity' => 2, 'price_cents' => 500]);

    $this->get('/lathe/resources/order-items/'.$item->getKey())->assertInertia(fn (AssertableInertia $page) => $page
        ->where('breadcrumbs.2.label', 'ORD-00099454'));
});

it('keeps the key of a parent the user may not view', function () {
    $item = OrderItemModel::query()->forceCreate(['order_id' => 12, 'product_id' => 1, 'quantity' => 2, 'price_cents' => 500]);
    Gate::before(fn ($user, string $ability, array $arguments) => $ability === 'view' && ($arguments[0] ?? null) instanceof OrderModel ? false : null);

    $this->get('/lathe/resources/order-items/'.$item->getKey())->assertInertia(fn (AssertableInertia $page) => $page
        ->where('breadcrumbs.2.label', '#12'));
});
