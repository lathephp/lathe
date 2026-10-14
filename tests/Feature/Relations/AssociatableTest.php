<?php

namespace Lathe\Tests\Feature\Relations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class AssociatableSetup
{
    public static bool $reorder = true;
}

class AssociatableCustomer extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->title('name')->searchable(['name'])->pickerResults(10)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::text('Name')];
    }
}

class AssociatableOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number')->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Number'),
            Field::belongsTo('Customer')->resource(AssociatableCustomer::class)->reorderAssociatables(AssociatableSetup::$reorder),
        ];
    }
}

/**
 * Run the request and keep the SQL of every query, with the identifier quotes removed so
 * the assertions read the same on every database.
 *
 * @return list<string>
 */
function associatableQueries(\Closure $request): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = str_replace(['"', '`'], '', $query->sql);
    });
    $request();

    return $queries;
}

function insertCustomers(string $prefix, int $count): void
{
    DB::table('customers')->insert(array_map(fn (int $i) => [
        'name' => sprintf('%s %02d', $prefix, $i),
        'email' => strtolower($prefix).$i.'@example.com',
    ], range(1, $count)));
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(AssociatableCustomer::class, AssociatableOrder::class);
    AssociatableSetup::$reorder = true;
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('lists the customers whose name matches the term on the create form', function () {
    $json = $this->getJson('/lathe/api/resources/orders/associatable/customer?q=ann')->assertOk()->json();

    expect(array_keys($json))->toBe(['items', 'has_more'])
        ->and(count($json['items']))->toBeGreaterThanOrEqual(3);

    foreach ($json['items'] as $item) {
        expect($item)->toHaveKeys(['value', 'display'])
            ->and(strtolower($item['display']))->toStartWith('ann');
    }
});

it('pages 25 at a time from one select with no count and no parent query', function () {
    insertCustomers('Zed', 30);

    $first = null;
    $queries = associatableQueries(function () use (&$first) {
        $first = $this->getJson('/lathe/api/resources/orders/associatable/customer?q=zed')->assertOk()->json();
    });
    $second = $this->getJson('/lathe/api/resources/orders/associatable/customer?q=zed&page=2')->assertOk()->json();
    $customerSelects = array_values(array_filter($queries, fn (string $sql) => str_starts_with(strtolower($sql), 'select') && str_contains($sql, 'customers')));

    expect($first['items'])->toHaveCount(25)
        ->and($first['has_more'])->toBeTrue()
        ->and($second['items'])->toHaveCount(5)
        ->and($second['has_more'])->toBeFalse()
        ->and($customerSelects)->toHaveCount(1)
        ->and($customerSelects[0])->toContain('limit 26');

    foreach ($queries as $sql) {
        expect(strtolower($sql))->not->toContain('count(')
            ->and($sql)->not->toContain('orders');
    }
});

it('ignores per_page and takes the page size from the Resource and the config', function () {
    insertCustomers('Zed', 30);

    expect($this->getJson('/lathe/api/resources/orders/associatable/customer?q=zed&per_page=100')->json('items'))->toHaveCount(25)
        ->and($this->getJson('/lathe/api/resources/associatable-orders/associatable/customer?q=zed')->json('items'))->toHaveCount(10);

    config(['lathe.limits.picker_results' => 5]);

    expect($this->getJson('/lathe/api/resources/orders/associatable/customer?q=zed')->json('items'))->toHaveCount(5);
});

it('refuses a term that is too long or has too many words', function (string $term) {
    $this->getJson('/lathe/api/resources/orders/associatable/customer?q='.urlencode($term))
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
})->with([
    '101 characters' => [str_repeat('a', 101)],
    '9 words' => ['a b c d e f g h i'],
]);

it('sorts by title and breaks a tie on the key', function () {
    insertCustomers('Zed', 2);
    $first = DB::table('customers')->insertGetId(['name' => 'Zed Same', 'email' => 'same1@example.com']);
    $second = DB::table('customers')->insertGetId(['name' => 'Zed Same', 'email' => 'same2@example.com']);

    $queries = associatableQueries(fn () => $this->getJson('/lathe/api/resources/orders/associatable/customer?q=zed')
        ->assertOk()
        ->assertJsonPath('items.*.value', [
            DB::table('customers')->where('name', 'Zed 01')->value('id'),
            DB::table('customers')->where('name', 'Zed 02')->value('id'),
            $first,
            $second,
        ]));

    expect(end($queries))->toContain('order by customers.name asc, customers.id asc');
});

it('keeps the query order and still ends on the key without reordering', function () {
    AssociatableSetup::$reorder = false;

    $queries = associatableQueries(fn () => $this->getJson('/lathe/api/resources/associatable-orders/associatable/customer?q=ann')->assertOk());

    expect(end($queries))->toEndWith('order by customers.id asc limit 11 offset 0')
        ->and(end($queries))->not->toContain('customers.name asc');
});

it('lists the same shape for a saved record', function () {
    $json = $this->getJson('/lathe/api/resources/orders/12/customer/associatable?q=ann')->assertOk()->json();

    expect(array_keys($json))->toBe(['items', 'has_more'])
        ->and(count($json['items']))->toBeGreaterThanOrEqual(3);
});

it('answers 404 for a missing record, an unknown field or a field that is not a relation', function (string $uri) {
    $this->getJson($uri)->assertNotFound();
})->with([
    'missing record' => ['/lathe/api/resources/orders/99999999/customer/associatable'],
    'unknown field' => ['/lathe/api/resources/orders/associatable/nothing'],
    'not a relation' => ['/lathe/api/resources/orders/associatable/number'],
]);

it('needs create on the parent for the create lookup', function () {
    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());

    $this->getJson('/lathe/api/resources/orders/associatable/customer?q=ann')->assertForbidden();
});
