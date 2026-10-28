<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Filters\BooleanFilter;
use Lathe\Filters\DateFilter;
use Lathe\Filters\Filter;
use Lathe\Filters\RangeFilter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Filters\OrderCustomer;
use Workbench\App\Lathe\Filters\OrderStatus;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class FilterTestTag extends Filter
{
    public function key(): string
    {
        return 'tag';
    }

    public function options(LatheRequest $request): array
    {
        return ['Pending' => 'pending', 'Paid' => 'paid', 'Shipped' => 'shipped', 'Cancelled' => 'cancelled'];
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), (array) $value);
    }
}

class FilterTestTotal extends RangeFilter
{
    public function key(): string
    {
        return 'total';
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query
            ->when($value['min'] !== null, fn (Builder $q) => $q->where($q->qualifyColumn('total_cents'), '>=', $value['min'] * 100))
            ->when($value['max'] !== null, fn (Builder $q) => $q->where($q->qualifyColumn('total_cents'), '<=', $value['max'] * 100));
    }
}

class FilterTestPlaced extends DateFilter
{
    public function key(): string
    {
        return 'placed';
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query
            ->when($value['from'] !== null, fn (Builder $q) => $q->where($q->qualifyColumn('placed_at'), '>=', $value['from'].' 00:00:00'))
            ->when($value['to'] !== null, fn (Builder $q) => $q->where($q->qualifyColumn('placed_at'), '<=', $value['to'].' 23:59:59'));
    }
}

class FilterTestFlags extends BooleanFilter
{
    public function key(): string
    {
        return 'flags';
    }

    public function options(LatheRequest $request): array
    {
        return ['Paid' => 'paid', 'Shipped' => 'shipped'];
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), array_keys(array_filter($value)));
    }
}

class FilterTestSecret extends Filter
{
    public function key(): string
    {
        return 'secret';
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query;
    }
}

class FilteredOrder extends OrderResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function filters(LatheRequest $request): array
    {
        // Status as a single select, which the workbench's Orders widens to a list.
        return [
            new OrderStatus,
            new OrderCustomer,
            (new FilterTestTag)->multiple(),
            new FilterTestTotal,
            new FilterTestPlaced,
            new FilterTestFlags,
            (new FilterTestSecret)->canSee(fn () => false),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    Lathe::registerResources(FilteredOrder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * @return list<array<string, mixed>>
 */
function filteredRows(string $resource, string $query): array
{
    return test()->getJson("/lathe/api/resources/{$resource}?per_page=100&{$query}")->assertOk()->json('table.rows');
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return list<Order>
 */
function ordersOf(array $rows): array
{
    return Order::whereIn('id', array_column($rows, 'id'))->get()->all();
}

it('shows only pending orders for filter[status]=pending, on the API and the page', function () {
    $rows = filteredRows('orders', 'filter[status]=pending');

    expect($rows)->toHaveCount(100)
        ->and(array_unique(array_column(array_column(array_column($rows, 'cells'), 'status'), 'value')))->toBe(['pending']);

    $this->get('/lathe/resources/orders?per_page=100&filter[status]=pending')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Index', shouldExist: false)
            ->where('table.rows', fn ($pageRows) => array_column($pageRows->all(), 'id') === array_column($rows, 'id'))
            ->where('filters.applied', ['status' => ['pending']])
            ->has('filters.available', 3));
});

it('narrows filter[customer]=12 to the orders of customer 12', function () {
    $rows = filteredRows('orders', 'filter[customer]=12');

    expect(array_column($rows, 'id'))->not->toBeEmpty()
        ->toBe(Order::where('customer_id', 12)->orderByDesc('id')->limit(100)->pluck('id')->all());
});

test('filters_in_class_value_shape_are_applied', function (string $query, Closure $matches, array $applied) {
    $response = $this->getJson("/lathe/api/resources/filtered-orders?per_page=100&{$query}")->assertOk();
    $orders = ordersOf($response->json('table.rows'));

    expect($orders)->not->toBeEmpty()
        ->and(array_filter($orders, fn (Order $order) => ! $matches($order)))->toBe([])
        ->and($response->json('filters.applied'))->toBe($applied);
})->with([
    'scalar' => ['filter[status]=paid', fn (Order $o) => $o->status === 'paid', ['status' => 'paid']],
    'list' => ['filter[tag][]=paid&filter[tag][]=shipped', fn (Order $o) => in_array($o->status, ['paid', 'shipped'], true), ['tag' => ['paid', 'shipped']]],
    'range min' => ['filter[total][min]=2000', fn (Order $o) => $o->total_cents >= 200000, ['total' => ['min' => 2000, 'max' => null]]],
    'range max' => ['filter[total][max]=10', fn (Order $o) => $o->total_cents <= 1000, ['total' => ['min' => null, 'max' => 10]]],
    'range both' => ['filter[total][min]=10&filter[total][max]=20', fn (Order $o) => $o->total_cents >= 1000 && $o->total_cents <= 2000, ['total' => ['min' => 10, 'max' => 20]]],
    'date' => ['filter[placed][from]='.now()->subDays(100)->toDateString().'&filter[placed][to]='.now()->subDays(50)->toDateString(),
        fn (Order $o) => $o->placed_at->toDateString() >= now()->subDays(100)->toDateString() && $o->placed_at->toDateString() <= now()->subDays(50)->toDateString(),
        ['placed' => ['from' => now()->subDays(100)->toDateString(), 'to' => now()->subDays(50)->toDateString()]]],
    'boolean' => ['filter[flags][paid]=1&filter[flags][shipped]=0', fn (Order $o) => $o->status === 'paid', ['flags' => ['paid' => true, 'shipped' => false]]],
])->group('regression');

it('answers 422 with errors.filter for a bad filter, never 500', function (string $query) {
    $this->getJson("/lathe/api/resources/filtered-orders?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['filter']);
})->with([
    'unknown key' => ['filter[nothing]=1'],
    'malformed range' => ['filter[total][min]=abc'],
    'hidden by canSee' => ['filter[secret]=1'],
    'a list for a single select' => ['filter[status][]=paid'],
    'an option it does not offer' => ['filter[tag][]=lost'],
    'a bad date' => ['filter[placed][from]=2026-13-01'],
    'a filter that is not a map' => ['filter=paid'],
    'a decimal for an integer column' => ['filter[field_total_cents][min]=10.5'],
    'an exponent for an integer column' => ['filter[field_total_cents][max]=1e20'],
    'an integer past PHP_INT_MAX' => ['filter[field_total_cents][min]=99999999999999999999'],
    'a remote value the filter refuses' => ['filter[customer]=abc'],
]);

it('answers 422 for a malformed or hidden filter on the page too', function (string $query) {
    $this->get("/lathe/resources/filtered-orders?{$query}")->assertStatus(422);
})->with([
    'malformed range' => ['filter[total][min]=abc'],
    'hidden by canSee' => ['filter[secret]=1'],
]);

it('drops a removed filter key from a page link and names it in staleFilters', function () {
    $unfiltered = filteredRows('orders', 'filter[status]=pending');

    $this->get('/lathe/resources/orders?per_page=100&filter[status]=pending&filter[gone]=1')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Index', shouldExist: false)
            ->where('staleFilters', ['gone'])
            ->where('filters.applied', ['status' => ['pending']])
            ->where('table.rows', fn ($rows) => array_column($rows->all(), 'id') === array_column($unfiltered, 'id')));

    $this->getJson('/lathe/api/resources/orders?filter[gone]=1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['filter']);
});

it('lists Status with four inline options and Customer as a remote picker', function () {
    $filters = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('filters');

    expect($filters['applied'])->toBe([])
        ->and($filters['available'])->toBe([
            [
                'key' => 'status',
                'name' => 'Status',
                'component' => 'multi-select-filter',
                'options' => [
                    ['label' => 'Pending', 'value' => 'pending'],
                    ['label' => 'Paid', 'value' => 'paid'],
                    ['label' => 'Shipped', 'value' => 'shipped'],
                    ['label' => 'Cancelled', 'value' => 'cancelled'],
                ],
                'remote' => false,
                'immediate' => false,
                'placement' => 'panel',
                'default' => null,
                'token' => 'status',
                'kind' => 'multiple',
            ],
            [
                'key' => 'customer',
                'name' => 'Customer',
                'component' => 'select-filter',
                'options' => null,
                'remote' => true,
                'immediate' => false,
                'placement' => 'panel',
                'default' => null,
                'token' => 'customer',
                'kind' => 'relation',
            ],
            [
                'key' => 'field_total_cents',
                'name' => 'Total',
                'component' => 'range-filter',
                'options' => [],
                'remote' => false,
                'immediate' => false,
                'placement' => 'panel',
                'default' => null,
                // The column holds cents and shows amounts, so the inputs take amounts.
                'scale' => 100,
                'token' => 'total',
                'kind' => 'range',
            ],
        ]);
});
