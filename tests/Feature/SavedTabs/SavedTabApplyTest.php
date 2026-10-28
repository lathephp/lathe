<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Http\Requests\LatheRequest;
use Lathe\SavedTabs\SavedTab;
use Lathe\SavedTabs\SchemaHash;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($this->admin);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function savedTabApplyMake(User $user, array $attributes = []): SavedTab
{
    return SavedTab::create($attributes + [
        'user_id' => (string) $user->getKey(),
        'resource_class' => Order::class,
        'name' => 'Paid',
        'filters' => ['status' => 'paid'],
        'position' => 1,
        'schema_hash' => SchemaHash::for(Order::make(), LatheRequest::createFrom(request())),
    ]);
}

/**
 * @return array<string, string>
 */
function savedTabApplyPartial(string $props): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Resources/Index',
        'X-Inertia-Partial-Data' => $props,
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];
}

it('applies the stored filters, search and sort of a saved tab', function () {
    $tab = savedTabApplyMake($this->admin, ['q' => 'ORD-1', 'sort' => '-total_cents']);
    $expected = OrderModel::where('status', 'paid')->where('number', 'like', '%ORD-1%')
        ->orderByDesc('total_cents')->orderByDesc('id')->limit(25)->pluck('id')->all();

    $json = $this->getJson("/lathe/api/resources/orders?saved_tab={$tab->id}")->assertOk()->json();

    expect($expected)->not->toBeEmpty()
        ->and(array_column($json['table']['rows'], 'id'))->toBe($expected)
        ->and($json['state']['sort'])->toBe('-total_cents')
        ->and($json['tabs']['activeSaved'])->toBe($tab->id);
});

it('lets a user filter narrow a saved tab', function () {
    $tab = savedTabApplyMake($this->admin);
    $expected = OrderModel::where('status', 'paid')->where('customer_id', 12)->count();

    $json = $this->getJson("/lathe/api/resources/orders?saved_tab={$tab->id}&filter[customer]=12&per_page=100")->assertOk()->json();

    expect($expected)->toBeGreaterThan(0)->and($json['table']['rows'])->toHaveCount($expected);
});

it('answers 404 for another user tab and for an unknown id', function () {
    $other = savedTabApplyMake(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->getJson("/lathe/api/resources/orders?saved_tab={$other->id}")->assertNotFound();
    $this->getJson('/lathe/api/resources/orders?saved_tab=99999')->assertNotFound();
});

it('answers 404 for a saved_tab past PHP_INT_MAX', function () {
    $this->getJson('/lathe/api/resources/orders?saved_tab=99999999999999999999')->assertNotFound();
});

it('lets the request sort win over the stored sort', function () {
    $tab = savedTabApplyMake($this->admin, ['sort' => '-total_cents']);

    $json = $this->getJson("/lathe/api/resources/orders?saved_tab={$tab->id}&sort=total_cents")->assertOk()->json();

    expect($json['state']['sort'])->toBe('total_cents');
});

it('saved_tab_sort_is_whitelisted', function () {
    $tab = savedTabApplyMake($this->admin, ['sort' => '-nope']);
    $plain = $this->getJson('/lathe/api/resources/orders?filter[status]=paid')->assertOk()->json();

    $json = $this->getJson("/lathe/api/resources/orders?saved_tab={$tab->id}")->assertOk()->json();

    expect(array_column($json['table']['rows'], 'id'))->toBe(array_column($plain['table']['rows'], 'id'))
        ->and($json['state']['sort'])->toBe('')
        ->and($json['tabs']['saved'][0]['stale'])->toBeTrue();
})->group('regression');

it('applies what still validates and marks the tab stale', function () {
    $tab = savedTabApplyMake($this->admin, ['filters' => ['status' => 'paid', 'retired' => 'x'], 'schema_hash' => 'old']);
    $expected = OrderModel::where('status', 'paid')->orderByDesc('id')->limit(100)->pluck('id')->all();

    $json = $this->getJson("/lathe/api/resources/orders?saved_tab={$tab->id}&per_page=100")->assertOk()->json();

    expect(array_column($json['table']['rows'], 'id'))->toBe($expected)
        ->and($json['tabs']['saved'])->toHaveCount(1)
        ->and($json['tabs']['saved'][0])->toMatchArray([
            'id' => $tab->id,
            'name' => 'Paid',
            'badgeType' => null,
            'showCount' => false,
            'stale' => true,
            // The workbench's Status takes a list, so the stored scalar comes back as one.
            'filters' => ['status' => ['paid']],
        ]);
});

it('marks a tab stale when only a stored filter key is dead', function () {
    $tab = savedTabApplyMake($this->admin, ['filters' => ['retired' => 'x']]);

    $this->getJson('/lathe/api/resources/orders')->assertJsonPath('tabs.saved.0.stale', true);
    $this->getJson("/lathe/api/resources/orders?saved_tab={$tab->id}")->assertOk();
});

it('counts a show_count saved tab under saved:<id> with the cap', function () {
    $tab = savedTabApplyMake($this->admin, ['show_count' => true]);
    $quiet = savedTabApplyMake($this->admin, ['name' => 'Quiet', 'position' => 2]);

    config(['lathe.limits.count_cap' => 3]);
    $counts = $this->get('/lathe/resources/orders?filter[status]=pending', savedTabApplyPartial('tabCounts'))->assertOk()->json('props.tabCounts');

    expect($counts["saved:{$tab->id}"]['count'])->toMatchArray(['value' => 3, 'capped' => true])
        ->and($counts)->not->toHaveKey("saved:{$quiet->id}");
});

it('sends an open saved view its search and the names of the customers it filters by, for the filter bar', function () {
    $customer = Workbench\App\Models\Customer::query()->firstOrFail();
    $tab = savedTabApplyMake($this->admin, ['filters' => ['customer' => (string) $customer->id], 'q' => 'ORD']);

    $json = $this->getJson("/lathe/api/resources/orders?saved_tab={$tab->id}")->assertOk()->json();

    expect($json['tabs']['saved'][0]['q'])->toBe('ORD')
        ->and($json['filters']['labels'])->toBe(['customer' => [(string) $customer->id => $customer->name]]);
});
