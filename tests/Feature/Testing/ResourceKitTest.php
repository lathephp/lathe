<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('reads one record from its detail page as Inertia JSON', function () {
    LatheTest::resource('orders')->show(12)
        ->assertOk()
        ->assertJsonPath('component', 'Resources/Detail')
        ->assertJsonPath('props.record.id', 12)
        ->assertJsonPath('props.record.title', 'ORD-00099454');
});

it('lists the lenses the index page offers', function () {
    LatheTest::resource('orders')->lenses()
        ->assertOk()
        ->assertJsonPath('props.lenses', [['uriKey' => 'pending-orders', 'name' => 'Pending Orders']]);
});

it('reads the filters of a Resource and of a lens', function () {
    $resource = LatheTest::resource('orders');

    expect(array_column($resource->filters()->assertOk()->json('filters'), 'key'))->toBe(['status', 'customer', 'total_cents'])
        ->and(array_column($resource->lens('pending-orders')->filters()->assertOk()->json('filters'), 'key'))->toBe(['status', 'customer']);
});

it('queries a lens index and counts its rows', function () {
    $pending = LatheTest::resource('orders')->lens('pending-orders');

    $pending->index()->assertOk()->assertJsonPath('lens.uriKey', 'pending-orders');
    $pending->count()->assertOk()->assertJsonPath('total', 0);
    LatheTest::resource('orders')->count()->assertOk()->assertJsonPath('total', 0);
});

it('searches every globally searchable Resource', function () {
    $orders = collect(LatheTest::search('ORD-00099454')->assertOk()->json('groups'))->firstWhere('resourceName', 'orders');

    expect(array_column($orders['results'], 'title'))->toBe(['ORD-00099454']);
});

it('creates, lists, renames, reorders and deletes saved tabs', function () {
    $orders = LatheTest::resource('orders');

    $paid = $orders->createSavedTab(['name' => 'Paid', 'filters' => ['status' => 'paid']])->assertCreated()->json('id');
    $late = $orders->createSavedTab(['name' => 'Late'])->assertCreated()->json('id');

    $orders->updateSavedTab($paid, ['name' => 'Settled'])->assertOk()->assertJsonPath('name', 'Settled');
    $orders->reorderSavedTabs([$late, $paid])->assertNoContent();
    expect(array_column($orders->savedTabs()->assertOk()->json('tabs'), 'name'))->toBe(['Late', 'Settled']);

    $orders->deleteSavedTab($late)->assertNoContent();
    expect(array_column($orders->savedTabs()->json('tabs'), 'name'))->toBe(['Settled']);
});
