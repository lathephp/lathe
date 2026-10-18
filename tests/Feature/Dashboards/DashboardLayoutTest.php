<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;
use Lathe\Preferences\UserPreference;
use Lathe\Tests\Fixtures\Dashboards\Cards;
use Lathe\Tests\Fixtures\Dashboards\Fixed;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Lathe\Tests\Fixtures\Widgets\ValueCustomers;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => in_array($user->email, ['admin@lathephp.com', 'editor@lathephp.com'], true));
    config(['inertia.testing.ensure_pages_exist' => false]);
});

afterEach(function () {
    Cards::$widgets = null;
});

function layoutAs(string $email): User
{
    $user = User::where('email', $email)->firstOrFail();
    test()->actingAs($user);

    return $user;
}

const LAYOUT_URL = '/lathe/api/dashboards/main/layout';

function customLayout(): array
{
    return ['widgets' => [
        ['uriKey' => 'revenue', 'width' => '1/3', 'hidden' => false],
        ['uriKey' => 'orders', 'width' => '1/3', 'hidden' => false],
        ['uriKey' => 'paid-orders', 'width' => '1/3', 'hidden' => true],
        ['uriKey' => 'orders-per-day', 'width' => '2/3', 'hidden' => false],
        ['uriKey' => 'orders-by-status', 'width' => '1/2', 'hidden' => false],
    ]];
}

it('lays the page out as the user saved it and leaves hidden widgets out', function () {
    layoutAs('admin@lathephp.com');

    $this->putJson(LAYOUT_URL, customLayout())->assertOk();

    $this->get('/lathe')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('dashboard.customized', true)
        ->where('dashboard.customizable', true)
        ->where('widgets', fn ($widgets) => array_column($widgets->all(), 'uriKey') === ['revenue', 'orders', 'orders-per-day', 'orders-by-status'])
        ->where('widgets.3.columns', 6)
        ->where('widgets.3.width', '1/2')
        ->where('hiddenWidgets', fn ($hidden) => array_column($hidden->all(), 'uriKey') === ['paid-orders'])
        ->missing('widgetValue:paid-orders'));
});

it('gives another user the code layout', function () {
    layoutAs('admin@lathephp.com');
    $this->putJson(LAYOUT_URL, customLayout())->assertOk();

    layoutAs('editor@lathephp.com');

    $this->get('/lathe')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('dashboard.customized', false)
        ->where('widgets', fn ($widgets) => array_column($widgets->all(), 'uriKey') === ['orders', 'revenue', 'paid-orders', 'orders-per-day', 'orders-by-status'])
        ->where('hiddenWidgets', []));
});

it('resets to the code layout on delete', function () {
    layoutAs('admin@lathephp.com');
    $this->putJson(LAYOUT_URL, customLayout())->assertOk();

    $this->deleteJson(LAYOUT_URL)->assertNoContent();

    expect(UserPreference::count())->toBe(0);
    $this->get('/lathe')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('dashboard.customized', false)
        ->where('widgets', fn ($widgets) => array_column($widgets->all(), 'uriKey') === ['orders', 'revenue', 'paid-orders', 'orders-per-day', 'orders-by-status'])
        ->where('widgets.3.columns', 8)
        ->where('hiddenWidgets', []));
});

it('ages a saved layout when the widgets change in code', function () {
    $admin = layoutAs('admin@lathephp.com');
    Lathe::registerDashboards(Cards::class);
    UserPreference::create(['user_id' => (string) $admin->getKey(), 'key' => 'dashboard:cards', 'value' => [
        ['uriKey' => 'value-orders', 'width' => '1/2', 'hidden' => false],
        ['uriKey' => 'removed-widget', 'width' => '1/2', 'hidden' => false],
        ['uriKey' => 'order-count', 'width' => '1/3', 'hidden' => false],
    ]]);

    Cards::$widgets = [new OrderCount, new ValueOrders, new ValueCustomers];

    $this->get('/lathe/dashboards/cards')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('widgets', fn ($widgets) => array_column($widgets->all(), 'uriKey') === ['value-orders', 'order-count', 'value-customers']));
});

it('stores the second of two saves and answers both', function () {
    layoutAs('admin@lathephp.com');

    $first = customLayout();
    $second = customLayout();
    $second['widgets'][0]['hidden'] = true;

    $this->putJson(LAYOUT_URL, $first)->assertOk()->assertJsonPath('layout.0.hidden', false);
    $this->putJson(LAYOUT_URL, $second)->assertOk()->assertJsonPath('layout.0.hidden', true);

    expect(UserPreference::where('key', 'dashboard:main')->count())->toBe(1)
        ->and(UserPreference::where('key', 'dashboard:main')->first()->value[0]['hidden'])->toBeTrue();
});

it('answers the layout aged against the code', function () {
    layoutAs('admin@lathephp.com');

    $this->putJson(LAYOUT_URL, ['widgets' => [['uriKey' => 'revenue', 'width' => 'full', 'hidden' => false], ['uriKey' => 'nope', 'width' => 'full', 'hidden' => false]]])
        ->assertOk()
        ->assertJsonPath('layout.0', ['uriKey' => 'revenue', 'width' => 'full', 'hidden' => false])
        ->assertJsonCount(5, 'layout')
        ->assertJsonPath('layout.1', ['uriKey' => 'orders', 'width' => '1/3', 'hidden' => false]);
});

it('refuses a dashboard that is not customizable', function () {
    $admin = layoutAs('admin@lathephp.com');
    Lathe::registerDashboards(Fixed::class);
    UserPreference::create(['user_id' => (string) $admin->getKey(), 'key' => 'dashboard:fixed', 'value' => [['uriKey' => 'order-count', 'width' => 'full', 'hidden' => true]]]);

    $this->getJson('/lathe/api/dashboards/fixed')->assertOk()->assertJsonPath('dashboard.customizable', false);
    $this->putJson('/lathe/api/dashboards/fixed/layout', ['widgets' => [['uriKey' => 'order-count', 'width' => 'full', 'hidden' => false]]])->assertForbidden();
    $this->deleteJson('/lathe/api/dashboards/fixed/layout')->assertForbidden();

    $this->get('/lathe/dashboards/fixed')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('dashboard.customized', false)
        ->where('widgets.0.width', '1/3')
        ->where('hiddenWidgets', []));
});

it('answers 404 for an unknown dashboard and 403 for one the user cannot see', function () {
    layoutAs('admin@lathephp.com');
    $this->putJson('/lathe/api/dashboards/nope/layout', customLayout())->assertNotFound();
    $this->deleteJson('/lathe/api/dashboards/nope/layout')->assertNotFound();

    Lathe::registerDashboards(Fixed::make()->customizable()->canSee(fn () => false));
    $this->putJson('/lathe/api/dashboards/fixed/layout', customLayout())->assertForbidden();
});

it('refuses a malformed layout', function (array $body) {
    layoutAs('admin@lathephp.com');

    $this->putJson(LAYOUT_URL, $body)->assertUnprocessable();

    expect(UserPreference::count())->toBe(0);
})->with([
    'a width that is not a card width' => [['widgets' => [['uriKey' => 'orders', 'width' => 'huge', 'hidden' => false]]]],
    'hidden not a boolean' => [['widgets' => [['uriKey' => 'orders', 'width' => 'full', 'hidden' => 'yes']]]],
    'uriKey not a string' => [['widgets' => [['uriKey' => 7, 'width' => 'full', 'hidden' => false]]]],
    'widgets missing' => [[]],
]);
