<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Lathe;
use Lathe\Tests\Fixtures\Models\StringThing as StringThingModel;
use Lathe\Tests\Fixtures\Models\UlidThing as UlidThingModel;
use Lathe\Tests\Fixtures\Models\UuidThing as UuidThingModel;
use Lathe\Tests\Fixtures\Resources\JoinedCustomer;
use Lathe\Tests\Fixtures\Resources\PrefixCustomer;
use Lathe\Tests\Fixtures\Resources\StringThing;
use Lathe\Tests\Fixtures\Resources\UlidThing;
use Lathe\Tests\Fixtures\Resources\UuidThing;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * The row ids one index request returns, 100 to a page.
 *
 * @return list<int|string>
 */
function searchIds(string $resource, string $query): array
{
    $rows = test()->getJson("/lathe/api/resources/{$resource}?per_page=100&{$query}")->assertOk()->json('table.rows');

    return array_column($rows, 'id');
}

it('finds order 12 by its full number and by a prefix', function () {
    $number = Order::findOrFail(12)->number;
    $prefix = substr($number, 0, 8);
    $expected = Order::where('number', 'like', $prefix.'%')->orderByDesc('id')->pluck('id')->all();

    expect(searchIds('orders', 'q='.urlencode($number)))->toBe([12])
        ->and(searchIds('orders', 'q='.urlencode($prefix)))->toBe($expected)
        ->and(count($expected))->toBeGreaterThanOrEqual(1);
});

test('search_escapes_percent_underscore_and_backslash', function (string $term) {
    expect(searchIds('customers', 'q='.urlencode($term)))->toBe([]);
})->with(['%', '_', '\\'])->group('regression');

it('matches names by prefix, not anywhere inside', function () {
    Customer::forceCreate(['name' => 'Joanne Banner', 'email' => 'jb@example.com']);
    $names = Customer::whereKey(searchIds('customers', 'q=ann'))->pluck('name')->all();

    expect($names)->toContain('Ann Taylor', 'Anna Kowalski', 'Annette Fox')
        ->and($names)->not->toContain('Joanne Banner');
});

it('matches an email anywhere when it is declared contains and only at the start otherwise', function () {
    Lathe::registerResources(PrefixCustomer::class);

    expect(count(searchIds('customers', 'q=gmail')))->toBeGreaterThanOrEqual(3)
        ->and(searchIds('prefix-customers', 'q=gmail'))->toBe([]);
});

it('finds a customer by its key', function () {
    Customer::whereKey(12)->update(['name' => 'Twelve', 'email' => 'twelve@example.com']);

    expect(searchIds('customers', 'q=12'))->toContain(12);
});

it('matches nothing by key for a number above maxPrimaryKeySize', function () {
    Lathe::registerResources(PrefixCustomer::class);

    expect(searchIds('prefix-customers', 'q=12'))->toBe([12])
        ->and(searchIds('prefix-customers', 'q=100'))->toBe([]);
});

it('matches UUID, ULID and string keys by prefix', function (string $resource, string $table, string $model, string $column, string $key, string $other) {
    Schema::create($table, function (Blueprint $table) use ($column) {
        $table->{$column}('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
    Lathe::registerResources($resource);
    $model::forceCreate(['id' => $key, 'name' => 'Wanted']);
    $model::forceCreate(['id' => $other, 'name' => 'Other']);

    expect(searchIds($resource::uriKey(), 'q='.substr($key, 0, 8)))->toBe([$key]);
})->with([
    'uuid' => [UuidThing::class, 'uuid_things', UuidThingModel::class, 'uuid', '9f1c2b3a-5d4e-4f60-8a7b-1c2d3e4f5a6b', '9f1c2b3b-5d4e-4f60-8a7b-1c2d3e4f5a6b'],
    'ulid' => [UlidThing::class, 'ulid_things', UlidThingModel::class, 'ulid', '01j9x7k2m3n4p5q6r7s8t9v0wx', '01j9x7k3m3n4p5q6r7s8t9v0wx'],
    'string' => [StringThing::class, 'string_things', StringThingModel::class, 'string', 'sku-alpha-0001', 'sku-alpine-0001'],
]);

it('accepts a term up to the character and word limits and answers 422 above them', function (string $resource, string $term, int $status) {
    Lathe::registerResources(PrefixCustomer::class);

    $response = $this->getJson("/lathe/api/resources/{$resource}?q=".urlencode($term))->assertStatus($status);

    if ($status === 422) {
        $response->assertJsonValidationErrors(['q']);
    }
})->with([
    '100 chars' => ['customers', str_repeat('a', 100), 200],
    '101 chars' => ['customers', str_repeat('a', 101), 422],
    '8 words' => ['customers', 'a b c d e f g h', 200],
    '9 words' => ['customers', 'a b c d e f g h i', 422],
    '50 chars on a Resource cap' => ['prefix-customers', str_repeat('a', 50), 200],
    '51 chars on a Resource cap' => ['prefix-customers', str_repeat('a', 51), 422],
    '3 words on a Resource cap' => ['prefix-customers', 'a b c', 200],
    '4 words on a Resource cap' => ['prefix-customers', 'a b c d', 422],
]);

it('answers 422 for a q that is not a string', function () {
    $this->getJson('/lathe/api/resources/customers?q[]=a')->assertUnprocessable()->assertJsonValidationErrors(['q']);
});

it('collapses runs of whitespace and matches the phrase as one pattern', function () {
    $lee = Customer::forceCreate(['name' => 'Ann Lee', 'email' => 'lee@example.com']);
    $bindings = [];
    DB::listen(function ($event) use (&$bindings) {
        $bindings = [...$bindings, ...$event->bindings];
    });

    expect(searchIds('customers', 'q=ann%20%20%20lee'))->toBe([$lee->id])
        ->and(searchIds('customers', 'q=ann+lee'))->toBe([$lee->id])
        ->and($bindings)->toContain('ann lee%')->not->toContain('ann   lee%');
});

it('applies no search for an empty q', function () {
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = strtolower($event->sql);
    });

    expect(searchIds('customers', 'q='))->toBe(searchIds('customers', ''))->toHaveCount(100)
        ->and(implode("\n", $queries))->not->toContain('like ?');

    searchIds('customers', 'q=a');
    expect(implode("\n", $queries))->toContain('like ?');
});

it('qualifies the searched columns when indexQuery joins a table with the same names', function () {
    Schema::create('customer_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id');
        $table->string('name');
        $table->string('status');
    });
    DB::table('customer_notes')->insert([
        ['customer_id' => 1, 'name' => 'Annual review', 'status' => 'open'],
        ['customer_id' => 5, 'name' => 'Call back', 'status' => 'open'],
    ]);
    Lathe::registerResources(JoinedCustomer::class);
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });

    expect(searchIds('joined-customers', 'q=ann'))->toBe([1])
        ->and(searchIds('joined-customers', 'q=5'))->toBe([5]);

    $search = array_values(array_filter($queries, fn (string $sql) => str_contains(strtolower($sql), 'like ?')));
    expect($search)->not->toBeEmpty()
        ->and($search[0])->toContain(quotedSql('"customers"."name"'))
        ->and(implode("\n", $queries))->toContain(quotedSql('"customers"."id" = ?'));
});
