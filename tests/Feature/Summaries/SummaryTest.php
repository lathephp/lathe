<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\Field;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Summaries\ResourceSummaries;
use Lathe\Summaries\Summary;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class SummarizedThing extends Model
{
    protected $table = 'summarized_things';

    protected $guarded = [];

    public $timestamps = false;
}

class SummarizedThings extends Resource
{
    /** @var list<Field> */
    public static array $fields = [];

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(SummarizedThing::class);
    }

    public function fields(LatheRequest $request): array
    {
        return static::$fields;
    }
}

class SummarizedOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->with(['customer']);
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Credit', 'customer.credit')->summarize(Summary::sum()),
            Field::make('Quantity', 'items.quantity')->summarize(Summary::sum()),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
});

/**
 * @return array<string, string>
 */
function summaryPartial(string $props): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Resources/Index',
        'X-Inertia-Partial-Data' => $props,
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];
}

/**
 * The summaries prop of the Orders index, loaded the way the deferred counts visit loads it.
 *
 * @return array<string, mixed>|null
 */
function orderSummaries(string $query = ''): ?array
{
    test()->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    /** @var array<string, mixed>|null */
    return test()->get('/lathe/resources/orders?'.$query, summaryPartial('count,tabCounts,summaries'))->assertOk()->json('props.summaries');
}

/**
 * @param  class-string<resource>  $class
 * @return array{summaries: array<string, mixed>|null, queries: list<string>}
 */
function summarize(string $class, bool $paged = true): array
{
    $query = new ResourceQuery($class::make(), LatheRequest::createFrom(Request::create('/lathe/api/resources/things')));
    $rows = $query->rows();
    $fields = $class::make()->fields(LatheRequest::createFrom(Request::create('/')));
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = $event->sql;
    });

    $summaries = (new ResourceSummaries($query, $fields, $class::make()->definition()->getCountCap()))->compute($rows, $paged);

    return ['summaries' => $summaries, 'queries' => $queries];
}

function cents(int|string $cents): string
{
    return number_format((int) $cents / 100, 2, '.', '');
}

function summaryThings(): void
{
    Schema::create('summarized_things', function (Blueprint $table) {
        $table->id();
        $table->integer('quantity');
        $table->decimal('price', 8, 2);
        $table->integer('missing')->nullable();
        $table->string('status');
    });
    DB::table('summarized_things')->insert([
        ['quantity' => 1, 'price' => '1.25', 'missing' => null, 'status' => 'paid'],
        ['quantity' => 2, 'price' => '2.50', 'missing' => null, 'status' => 'pending'],
        ['quantity' => 6, 'price' => '3.75', 'missing' => null, 'status' => 'paid'],
    ]);
}

it('totals every order and the page shown in the Orders footer', function () {
    $this->seed(DemoSeeder::class);

    $summaries = orderSummaries();
    $page = DB::table('orders')->orderByDesc('id')->limit(25)->pluck('total_cents')->sum();

    expect($summaries['allRecords']['total_cents'])->toBe([['label' => 'Revenue', 'value' => cents((int) DB::table('orders')->sum('total_cents'))]])
        ->and($summaries['page']['total_cents'])->toBe([['label' => 'Revenue', 'value' => cents($page)]])
        ->and($summaries['allRecords']['total_cents'][0]['value'])->toMatch('/^\d+\.\d{2}$/')
        ->and($summaries['message'])->toBeNull();
});

it('leaves out the page totals when every row fits one page', function () {
    $this->seed(DemoSeeder::class);
    Order::where('id', '>', 20)->delete();

    $summaries = orderSummaries();

    expect($summaries['page'])->toBeNull()
        ->and($summaries['allRecords']['total_cents'][0]['value'])->toBe(cents((int) DB::table('orders')->sum('total_cents')));
});

it('totals all rows at the cap and only the page above it', function () {
    $this->seed(DemoSeeder::class);

    expect(orderSummaries()['allRecords'])->not->toBeNull();

    Order::forceCreate(['customer_id' => 1, 'number' => 'ORD-99999999', 'status' => 'paid', 'total_cents' => 100, 'placed_at' => now()]);
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = strtolower($event->sql);
    });
    $summaries = orderSummaries();
    $page = DB::table('orders')->orderByDesc('id')->limit(25)->pluck('total_cents')->sum();

    expect($summaries['allRecords'])->toBeNull()
        ->and($summaries['page']['total_cents'])->toBe([['label' => 'Revenue', 'value' => cents($page)]])
        ->and($summaries['message'])->toBe('Narrow the filters to total all rows')
        ->and(array_filter($queries, fn (string $sql) => str_contains($sql, 'sum(')))->toBe([]);
});

it('defers the summaries to the counts visit', function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());

    $first = $this->get('/lathe/resources/orders', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ])->assertOk()->json();

    expect($first['props'])->toHaveKey('table')->not->toHaveKey('summaries')
        ->and($first['deferredProps']['counts'])->toBe(['count', 'tabCounts', 'summaries'])
        ->and(orderSummaries())->toHaveKey('allRecords');
});

it('gives sum, average, count, min, max and range for int, decimal and null columns', function () {
    summaryThings();
    $all = [Summary::sum(), Summary::average(), Summary::count(), Summary::min(), Summary::max(), Summary::range()];
    SummarizedThings::$fields = [
        Field::make('Quantity')->summarize(...$all),
        Field::make('Price')->summarize(...$all),
        Field::make('Missing')->summarize(...$all),
    ];

    $summaries = summarize(SummarizedThings::class)['summaries'];
    $expected = [
        'quantity' => [
            ['label' => 'Sum', 'value' => '9'],
            ['label' => 'Average', 'value' => '3'],
            ['label' => 'Count', 'value' => '3'],
            ['label' => 'Min', 'value' => '1'],
            ['label' => 'Max', 'value' => '6'],
            ['label' => 'Range', 'value' => '1 - 6'],
        ],
        'price' => [
            ['label' => 'Sum', 'value' => '7.5'],
            ['label' => 'Average', 'value' => '2.5'],
            ['label' => 'Count', 'value' => '3'],
            ['label' => 'Min', 'value' => '1.25'],
            ['label' => 'Max', 'value' => '3.75'],
            ['label' => 'Range', 'value' => '1.25 - 3.75'],
        ],
        'missing' => [
            ['label' => 'Sum', 'value' => null],
            ['label' => 'Average', 'value' => null],
            ['label' => 'Count', 'value' => '0'],
            ['label' => 'Min', 'value' => null],
            ['label' => 'Max', 'value' => null],
            ['label' => 'Range', 'value' => null],
        ],
    ];

    expect($summaries['allRecords'])->toBe($expected)
        ->and($summaries['page'])->toBe($expected);
});

it('batches every summary into one query and totals the page in memory', function () {
    summaryThings();
    SummarizedThings::$fields = [
        Field::make('Quantity')->summarize(Summary::sum(), Summary::average()),
        Field::make('Price')->summarize(Summary::max()->label('Top')->format(fn (int|float|string $value) => '$'.$value)),
    ];

    ['summaries' => $summaries, 'queries' => $queries] = summarize(SummarizedThings::class);
    $aggregates = array_values(array_filter($queries, fn (string $sql) => ! str_contains($sql, 'lathe_count')));

    expect($queries)->toHaveCount(2)
        ->and($aggregates)->toHaveCount(1)
        ->and($aggregates[0])->toContain(quotedSql('sum("summarized_things"."quantity")'))
        ->toContain(quotedSql('avg("summarized_things"."quantity")'))
        ->toContain(quotedSql('max("summarized_things"."price")'))
        ->and($summaries['allRecords']['price'])->toBe([['label' => 'Top', 'value' => '$3.75']])
        ->and($summaries['page']['price'])->toBe([['label' => 'Top', 'value' => '$3.75']]);
});

it('runs a summary with its own query only for all records', function () {
    summaryThings();
    SummarizedThings::$fields = [
        Field::make('Quantity')->summarize(
            Summary::sum(),
            Summary::count()->query(fn ($query) => $query->where('status', 'paid'))->hideLabel(),
        ),
    ];

    ['summaries' => $summaries, 'queries' => $queries] = summarize(SummarizedThings::class);

    expect($queries)->toHaveCount(3)
        ->and(array_values(array_filter($queries, fn (string $sql) => str_contains($sql, quotedSql('"status" = ?')))))->toHaveCount(1)
        ->and($summaries['allRecords']['quantity'])->toBe([['label' => 'Sum', 'value' => '9'], ['label' => null, 'value' => '2']])
        ->and($summaries['page']['quantity'])->toBe([['label' => 'Sum', 'value' => '9']]);
});

it('joins a to-one path and subselects a to-many path', function () {
    DB::table('categories')->insert(['id' => 1, 'name' => 'Tools']);
    DB::table('products')->insert(['id' => 1, 'category_id' => 1, 'name' => 'Hammer', 'sku' => 'H-1', 'price_cents' => 100]);
    DB::table('customers')->insert([
        ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'country' => 'GB', 'credit' => '10.25'],
        ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'country' => 'GB', 'credit' => null],
    ]);
    foreach ([[1, 1], [2, 1], [3, 2]] as [$id, $customer]) {
        DB::table('orders')->insert(['id' => $id, 'customer_id' => $customer, 'number' => "ORD-{$id}", 'status' => 'paid', 'total_cents' => 100, 'placed_at' => now()]);
    }
    DB::table('order_items')->insert([
        ['order_id' => 1, 'product_id' => 1, 'quantity' => 2, 'price_cents' => 100],
        ['order_id' => 1, 'product_id' => 1, 'quantity' => 3, 'price_cents' => 100],
        ['order_id' => 2, 'product_id' => 1, 'quantity' => 4, 'price_cents' => 100],
    ]);

    ['summaries' => $summaries, 'queries' => $queries] = summarize(SummarizedOrders::class);
    $aggregate = $queries[1];

    expect($queries)->toHaveCount(2)
        ->and($aggregate)->toContain(quotedSql('left join "customers" on "orders"."customer_id" = "customers"."id"'))
        ->toContain(quotedSql('select sum("customers"."credit")'))
        ->not->toContain('*')
        ->toContain(quotedSql('(select sum("order_items"."quantity") from "order_items" where "order_items"."order_id" in (select "orders"."id" from "orders"'))
        ->and($summaries['allRecords'])->toBe([
            'customer' => [['label' => 'Sum', 'value' => '20.5']],
            'items' => [['label' => 'Sum', 'value' => '9']],
        ])
        ->and($summaries['page'])->toBe(['customer' => [['label' => 'Sum', 'value' => '20.5']]]);
});
