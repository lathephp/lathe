<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Cards::$widgets = [(new ValueOrders)->cacheAcrossUsers()];
    Lathe::registerDashboards(Cards::class);
    config(['inertia.testing.ensure_pages_exist' => false]);
});

function linkUser(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

it('points a card that counts orders at the orders index', function () {
    $this->actingAs(linkUser('admin@lathephp.com'));

    expect($this->getJson('/lathe/api/dashboards/cards/widgets/value-orders?range=30')->json())
        ->toMatchArray(['link' => '/lathe/resources/orders'])
        ->not->toHaveKey('_source');

    $this->get('/lathe/dashboards/cards')->assertInertia(fn (AssertableInertia $page) => $page
        ->loadDeferredProps(['value-orders'], fn (AssertableInertia $reload) => $reload
            ->where('widgetValue:value-orders.link', '/lathe/resources/orders')
            ->missing('widgetValue:value-orders._source')));
});

it('leaves the link off for a user who may not list orders, after the admin warmed the shared cache', function () {
    Gate::before(fn (User $user, string $ability) => $ability === 'viewAny' && $user->email === 'editor@lathephp.com' ? false : null);

    $this->actingAs(linkUser('admin@lathephp.com'))->getJson('/lathe/api/dashboards/cards/widgets/value-orders?range=30')->assertJsonPath('link', '/lathe/resources/orders');

    expect($this->actingAs(linkUser('editor@lathephp.com'))->getJson('/lathe/api/dashboards/cards/widgets/value-orders?range=30')->assertOk()->json())
        ->not->toHaveKey('link')
        ->not->toHaveKey('_source');
});

it('carries the link a card declares', function () {
    Cards::$widgets = [(new OrderCount)->link('/somewhere')];

    $this->actingAs(linkUser('admin@lathephp.com'))
        ->getJson('/lathe/api/dashboards/cards/widgets/order-count')
        ->assertJsonPath('link', '/somewhere');
});
