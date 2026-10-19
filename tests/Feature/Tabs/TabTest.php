<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tabs\Tab;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class TabbedOrder extends Order
{
    /** @var list<Tab> */
    public static array $tabs = [];

    public function tabs(LatheRequest $request): array
    {
        return static::$tabs;
    }
}

class SavedTabsOrder extends Order
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->savedTabs();
    }

    public function tabs(LatheRequest $request): array
    {
        return [];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
    TabbedOrder::$tabs = [];
});

/**
 * @param  array<string, mixed>  $json
 * @return list<mixed>
 */
function tabTestStatuses(array $json): array
{
    return array_values(array_unique(array_map(fn (array $row) => $row['cells']['status']['value'], $json['table']['rows'])));
}

function tabTestQuery(string $resource, string $query = ''): ResourceQuery
{
    return new ResourceQuery($resource::make(), LatheRequest::createFrom(Request::create('/lathe/api/resources/orders?'.$query)));
}

/**
 * @return array<string, string>
 */
function tabTestPartial(string $props): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Resources/Index',
        'X-Inertia-Partial-Data' => $props,
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];
}

it('narrows the rows to the chosen tab', function () {
    $json = $this->getJson('/lathe/api/resources/orders?tab=pending&per_page=100')->assertOk()->json();

    expect($json['table']['rows'])->not->toBeEmpty()
        ->and(tabTestStatuses($json))->toBe(['pending']);
});

it('answers 422 for an unknown tab on the API and the page', function () {
    $this->getJson('/lathe/api/resources/orders?tab=nope')->assertUnprocessable()->assertJsonValidationErrors('tab');
    $this->get('/lathe/resources/orders?tab=nope')->assertStatus(422);
});

it('reads the first tab as no tab', function () {
    $all = $this->getJson('/lathe/api/resources/orders?tab=all')->assertOk()->json('table.rows');
    $none = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.rows');

    expect($all)->toBe($none)->and($all)->toHaveCount(25);
});

it('lets the filters narrow inside a tab', function () {
    $expected = OrderModel::where('status', 'pending')->where('customer_id', 12)->count();
    $json = $this->getJson('/lathe/api/resources/orders?filter[customer]=12&tab=pending&per_page=100')->assertOk()->json();

    expect($expected)->toBeGreaterThan(0)
        ->and($json['table']['rows'])->toHaveCount($expected)
        ->and(tabTestStatuses($json))->toBe(['pending']);
});

it('counts the Pending badge without the user filters', function () {
    $pending = OrderModel::where('status', 'pending')->count();

    expect($pending)->toBeGreaterThan(0);

    $props = $this->get('/lathe/resources/orders?filter[status]=paid', tabTestPartial('tabCounts'))->assertOk()->json('props');

    // Every counted tab ignores the user's filters: All still counts every order under a Paid filter.
    expect($props['tabCounts']['pending']['count'])->toMatchArray(['value' => $pending, 'capped' => false])
        ->and($props['tabCounts']['all']['count']['value'])->toBe(OrderModel::count());
});

it('counts only the tabs that ask for a count', function () {
    TabbedOrder::$tabs = [
        Tab::make('All'),
        Tab::make('Pending')->modifyQueryUsing(fn (Builder $q) => $q->where($q->qualifyColumn('status'), 'pending'))->withCount(),
    ];
    $payload = new IndexPayload(LatheRequest::createFrom(Request::create('/lathe/api/resources/orders')), app(ResourceAuthorization::class));

    expect($payload->tabCounts(TabbedOrder::class))->toHaveKey('pending')->not->toHaveKey('all');
});

it('caps a badge above the cap', function () {
    config(['lathe.limits.count_cap' => 3]);

    $count = tabTestQuery(TabbedOrder::class)->tabCount(
        Tab::make('Pending')->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'pending')),
    );

    expect($count->value)->toBe(3)->and($count->capped)->toBeTrue();
});

it('hands badge closures a builder already narrowed by the tab', function () {
    $seen = null;
    TabbedOrder::$tabs = [
        Tab::make('Paid')
            ->modifyQueryUsing(fn (Builder $q) => $q->where($q->qualifyColumn('status'), 'paid'))
            ->badge(function (Builder $q) use (&$seen) {
                $seen = $q->toBase()->wheres;

                return $q->count();
            }),
    ];

    $query = tabTestQuery(TabbedOrder::class);
    $badge = $query->tabBadge($query->tabs()[0]);

    expect($badge)->toBe(OrderModel::where('status', 'paid')->count())
        ->and($seen)->toHaveCount(1)
        ->and($seen[0]['column'])->toBe('orders.status');
});

it('hides a tab that fails canSee and answers 422 for its key', function () {
    TabbedOrder::$tabs = [Tab::make('All'), Tab::make('Secret')->canSee(fn () => false)];

    expect(array_map(fn (Tab $tab) => $tab->key(), tabTestQuery(TabbedOrder::class)->tabs()))->toBe(['all'])
        ->and(fn () => tabTestQuery(TabbedOrder::class, 'tab=secret')->scoped())->toThrow(ValidationException::class);
});

it('adds an All tab with a count when saved tabs are on and none is declared', function () {
    $query = tabTestQuery(SavedTabsOrder::class);
    $tabs = $query->tabs();

    expect($tabs)->toHaveCount(1)
        ->and($tabs[0]->name())->toBe('All')
        ->and($tabs[0]->hasCount())->toBeTrue()
        ->and($query->tabCount($tabs[0])->value)->toBe(OrderModel::count());
});

it('sends no tabCounts on the first visit and both counts on one partial visit', function () {
    $this->get('/lathe/resources/orders')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Index', shouldExist: false)
            ->where('tabs.active', 'all')
            ->where('tabs.items.1.key', 'pending')
            ->missing('tabCounts')
            ->missing('count'));

    $props = $this->get('/lathe/resources/orders', tabTestPartial('count,tabCounts'))->assertOk()->json('props');

    expect($props['count']['value'])->toBe(OrderModel::count())
        ->and($props['tabCounts']['pending']['count']['value'])->toBe(OrderModel::where('status', 'pending')->count())
        ->and($props)->not->toHaveKey('table');
});

it('flags the tabs whose badge loads with the counts', function () {
    expect(Tab::make('Counted')->withCount()->toArray()['deferredBadge'])->toBeTrue()
        ->and(Tab::make('Closure')->badge(fn (Builder $query) => $query->count())->toArray()['deferredBadge'])->toBeTrue()
        ->and(Tab::make('Fixed')->badge(3)->toArray()['deferredBadge'])->toBeFalse()
        ->and(Tab::make('Plain')->toArray()['deferredBadge'])->toBeFalse();
});
