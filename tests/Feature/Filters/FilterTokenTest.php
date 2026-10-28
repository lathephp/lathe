<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lathe\Filters\BooleanFilter;
use Lathe\Filters\DateFilter;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Lathe\SavedTabs\SavedTab;
use Lathe\SavedTabs\SchemaHash;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class FilterTokenClash extends Filter
{
    public function key(): string
    {
        return 'total';
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query;
    }
}

class FilterTokenPlaced extends DateFilter
{
    public function key(): string
    {
        return 'placed';
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query;
    }
}

class FilterTokenFlags extends BooleanFilter
{
    public function key(): string
    {
        return 'flags';
    }

    public function options(LatheRequest $request): array
    {
        return ['Paid' => 'paid'];
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query;
    }
}

class FilterTokenOrder extends OrderResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public static function uriKey(): string
    {
        return 'token-orders';
    }

    public function filters(LatheRequest $request): array
    {
        return [...parent::filters($request), new FilterTokenClash, new FilterTokenPlaced, new FilterTokenFlags];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    (new DemoSeeder)->forOrders(40)->run();
    Gate::define('viewLathe', fn () => true);
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    $this->actingAs($this->admin);
});

/**
 * @return array<string, array{token: string, kind: string}>
 */
function filterTokens(string $url): array
{
    $tokens = [];

    foreach (test()->getJson($url)->assertOk()->json('filters.available') as $filter) {
        $tokens[$filter['key']] = ['token' => $filter['token'], 'kind' => $filter['kind']];
    }

    return $tokens;
}

it('sends each Orders filter its token key and kind', function () {
    expect(filterTokens('/lathe/api/resources/orders'))->toBe([
        'status' => ['token' => 'status', 'kind' => 'multiple'],
        'customer' => ['token' => 'customer', 'kind' => 'relation'],
        'field_total_cents' => ['token' => 'total', 'kind' => 'range'],
    ]);
});

it('keeps field_<attribute> for a field filter whose label clashes with another key', function () {
    Lathe::registerResources(FilterTokenOrder::class);

    $tokens = filterTokens('/lathe/api/resources/token-orders');

    expect($tokens['field_total_cents'])->toBe(['token' => 'field_total_cents', 'kind' => 'range'])
        ->and($tokens['total'])->toBe(['token' => 'total', 'kind' => 'text'])
        ->and($tokens['placed'])->toBe(['token' => 'placed', 'kind' => 'date'])
        ->and($tokens['flags'])->toBe(['token' => 'flags', 'kind' => 'boolean']);
});

it('narrows Orders to any of several statuses, and to one by the scalar form', function () {
    $both = $this->getJson('/lathe/api/resources/orders?per_page=100&filter[status][]=pending&filter[status][]=paid')->assertOk()->json('table.rows');
    $paid = $this->getJson('/lathe/api/resources/orders?per_page=100&filter[status]=paid')->assertOk()->json('table.rows');

    expect(count($both))->toBe(Order::whereIn('status', ['pending', 'paid'])->count())
        ->and(count($paid))->toBe(Order::where('status', 'paid')->count())
        ->and(Order::whereIn('id', array_column($paid, 'id'))->pluck('status')->unique()->all())->toBe(['paid']);
});

it('keeps a saved tab that stores one status as a scalar fresh', function () {
    $tab = SavedTab::create([
        'user_id' => (string) $this->admin->getKey(),
        'resource_class' => OrderResource::class,
        'name' => 'Paid',
        'filters' => ['status' => 'paid'],
        'position' => 1,
        'schema_hash' => SchemaHash::for(OrderResource::make(), LatheRequest::createFrom(request())),
    ]);

    $saved = $this->get('/lathe/resources/orders', ['X-Inertia' => 'true'])->assertOk()->json('props.tabs.saved');

    expect(collect($saved)->firstWhere('id', $tab->id)['stale'])->toBeFalse();
});
