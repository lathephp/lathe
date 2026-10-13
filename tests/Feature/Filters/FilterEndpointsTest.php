<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Filters\FieldFilter;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class EndpointsThresholdOrder extends OrderResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->filterRemoteThreshold(3);
    }
}

class EndpointsHiddenFilterOrder extends OrderResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function filters(LatheRequest $request): array
    {
        return [(new EndpointsSecretFilter)->canSee(fn () => false)];
    }
}

class EndpointsSecretFilter extends Filter
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

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    Lathe::registerResources(OrderResource::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('lists the explicit filters, then one generated for each filterable field', function () {
    $filters = $this->getJson('/lathe/api/resources/orders/filters')->assertOk()->json('filters');

    expect(array_column($filters, 'key'))->toBe(['status', 'customer', 'field_total_cents'])
        ->and(array_column($filters, 'name'))->toBe(['Status', 'Customer', 'Total'])
        ->and(array_column($filters, 'remote'))->toBe([false, true, false])
        ->and(array_column($filters[0]['options'], 'value'))->toBe(['pending', 'paid', 'shipped', 'cancelled'])
        ->and($filters[1]['options'])->toBeNull()
        ->and($filters[2]['component'])->toBe('range-filter');
});

it('makes a filter remote when its options pass the threshold of the Resource', function () {
    Lathe::registerResources(EndpointsThresholdOrder::class);

    $filters = $this->getJson('/lathe/api/resources/endpoints-threshold-orders/filters')->assertOk()->json('filters');

    expect($filters[0]['key'])->toBe('status')
        ->and($filters[0]['remote'])->toBeTrue()
        ->and($filters[0]['options'])->toBeNull();
});

it('serves customers whose name starts with the term, 25 to a page', function () {
    $body = $this->getJson('/lathe/api/resources/orders/filters/customer/options?q=ann&page=1')->assertOk()->json();

    expect(count($body['options']))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(25)
        ->and($body['options'][0])->toHaveKeys(['label', 'value'])
        ->and($body['hasMore'])->toBeBool()
        ->and(array_column($body['options'], 'label'))->toContain('Ann Taylor', 'Anna Kowalski', 'Annette Fox');

    foreach ($body['options'] as $option) {
        expect($option['label'])->toStartWith('Ann');
    }
});

it('pages all customers by id with an empty term', function () {
    $one = $this->getJson('/lathe/api/resources/orders/filters/customer/options?q=&page=1')->assertOk()->json();
    $two = $this->getJson('/lathe/api/resources/orders/filters/customer/options?q=&page=2')->assertOk()->json();

    expect($one['options'])->toHaveCount(25)
        ->and($two['options'])->toHaveCount(25)
        ->and($one['hasMore'])->toBeTrue()
        ->and($two['hasMore'])->toBeTrue()
        ->and(array_column($one['options'], 'value'))->toBe(range(1, 25))
        ->and(array_column($two['options'], 'value'))->toBe(range(26, 50));
});

it('says the last page has no more', function () {
    $body = $this->getJson('/lathe/api/resources/orders/filters/customer/options?q=&page=40')->assertOk()->json();

    expect($body['options'])->toHaveCount(25)
        ->and($body['hasMore'])->toBeFalse();
});

it('pages an inline filter by label prefix', function () {
    $body = $this->getJson('/lathe/api/resources/orders/filters/status/options?q=sh')->assertOk()->json();

    expect($body)->toBe(['options' => [['label' => 'Shipped', 'value' => 'shipped']], 'hasMore' => false]);
});

it('refuses a bad page or term, an unknown filter and an unknown Resource', function (string $url, int $status) {
    $this->getJson($url)->assertStatus($status);
})->with([
    'page zero' => ['/lathe/api/resources/orders/filters/customer/options?page=0', 422],
    'page text' => ['/lathe/api/resources/orders/filters/customer/options?page=two', 422],
    'long term' => ['/lathe/api/resources/orders/filters/customer/options?q='.str_repeat('a', 101), 422],
    'unknown filter' => ['/lathe/api/resources/orders/filters/nope/options', 404],
    'unknown resource' => ['/lathe/api/resources/nope/filters', 404],
]);

it('answers 404 for a filter canSee hides', function () {
    Lathe::registerResources(EndpointsHiddenFilterOrder::class);

    $this->getJson('/lathe/api/resources/endpoints-hidden-filter-orders/filters/secret/options')->assertNotFound();
});

it('returns the filterable fields and builds a field filter from one', function () {
    $resource = OrderResource::make();
    $request = app(LatheRequest::class);
    $fields = $resource->filterableFields($request);
    $filter = $fields[0]->makeFieldFilter();

    expect(array_map(fn (Field $field) => $field->label(), $fields))->toBe(['Total'])
        ->and($filter)->toBeInstanceOf(FieldFilter::class)
        ->and($filter->key())->toBe('field_total_cents')
        ->and($filter->name())->toBe('Total')
        ->and(array_map(fn (Filter $f) => $f->key(), $resource->availableFilters($request)))
        ->toBe(['status', 'customer', 'field_total_cents']);
});

it('narrows the rows by a generated field filter', function () {
    $rows = $this->getJson('/lathe/api/resources/orders?per_page=100&filter[field_total_cents][min]=100000')->assertOk()->json('table.rows');
    $orders = Order::whereIn('id', array_column($rows, 'id'))->get();

    expect($orders)->not->toBeEmpty()
        ->and($orders->min('total_cents'))->toBeGreaterThanOrEqual(100000);
});
