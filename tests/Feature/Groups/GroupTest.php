<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Exceptions\InvalidConfigurationException;
use Lathe\Field\Field;
use Lathe\Groups\Group;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Resources\ResourceRegistry;
use Lathe\Summaries\Summary;
use Workbench\App\Lathe\Resources\Order as OrderResource;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;
use Workbench\Database\Seeders\DemoSeeder;

class GroupThing extends Model
{
    protected $table = 'group_things';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['day' => 'date'];
    }
}

class GroupThings extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(GroupThing::class);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::make('Quantity')->summarize(Summary::sum())];
    }

    public function groups(LatheRequest $request): array
    {
        return [Group::make('tier'), Group::make('day')->date()];
    }
}

class DefaultGroupedOrders extends OrderResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->defaultGroup('status');
    }
}

class CountryGroupedOrders extends OrderResource
{
    public function groups(LatheRequest $request): array
    {
        return [Group::make('customer.country')];
    }
}

class ItemGroupedOrders extends OrderResource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function groups(LatheRequest $request): array
    {
        return [Group::make('items.quantity')];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
});

/**
 * @return array<string, string>
 */
function groupPartial(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Resources/Index',
        'X-Inertia-Partial-Data' => 'count,tabCounts,summaries',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];
}

function groupCents(int|string $cents): string
{
    return '$'.number_format((int) $cents / 100, 2);
}

function actAsGroupAdmin(): void
{
    test()->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
}

/**
 * The SQL and bindings of every query a callback ran.
 *
 * @return list<array{sql: string, bindings: list<mixed>}>
 */
function groupQueriesDuring(Closure $callback): array
{
    $queries = [];
    DB::listen(function ($event) use (&$queries) {
        $queries[] = ['sql' => str_replace(['"', '`'], '', $event->sql), 'bindings' => array_values($event->bindings)];
    });
    $callback();

    return $queries;
}

/**
 * @param  array<string, string>  $query
 */
function groupRequest(array $query): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/things', 'GET', $query));
}

/**
 * @param  class-string<resource>  $class
 * @param  array<string, string>  $query
 * @return array{payload: array<string, mixed>, summaries: array<string, mixed>|null}
 */
function groupPayload(string $class, array $query): array
{
    $payload = new IndexPayload(groupRequest($query), app(ResourceAuthorization::class));

    return ['payload' => $payload->build($class), 'summaries' => $payload->summaries($class)];
}

function groupThings(): void
{
    Schema::create('group_things', function (Blueprint $table) {
        $table->id();
        $table->string('tier')->nullable();
        $table->date('day');
        $table->integer('quantity');
    });
    DB::table('group_things')->insert([
        ['tier' => 'gold', 'day' => '2026-03-08', 'quantity' => 1],
        ['tier' => null, 'day' => '2026-03-08', 'quantity' => 2],
        ['tier' => null, 'day' => '2026-03-09', 'quantity' => 4],
        ['tier' => 'gold', 'day' => '2026-03-09', 'quantity' => 8],
    ]);
}

it('orders by status descending, then the sort, under "Status: paid" headers with one subtotal query', function () {
    app(DemoSeeder::class)->forOrders(40)->run();
    actAsGroupAdmin();

    $json = $this->getJson('/lathe/api/resources/orders?group=status:desc&sort=-total_cents&per_page=50')->assertOk()->json();
    $rows = collect($json['table']['rows']);
    $expected = DB::table('orders')->orderByDesc('status')->orderByDesc('total_cents')->orderByDesc('id')->pluck('id')->all();

    expect($rows->pluck('id')->all())->toBe($expected)
        ->and($rows->pluck('group.title')->unique()->values()->all())->toBe(['Status: shipped', 'Status: pending', 'Status: paid', 'Status: cancelled'])
        ->and($rows->pluck('group.key')->unique()->values()->all())->toBe(['shipped', 'pending', 'paid', 'cancelled'])
        ->and($json['groups'])->toBe([
            'available' => [['id' => 'status', 'label' => 'Status'], ['id' => 'placed', 'label' => 'Placed']],
            'active' => ['id' => 'status', 'direction' => 'desc', 'collapsible' => true],
        ]);

    $summaries = null;
    $queries = groupQueriesDuring(function () use (&$summaries) {
        $summaries = $this->get('/lathe/resources/orders?group=status:desc&sort=-total_cents&per_page=50', groupPartial())->assertOk()->json('props.summaries');
    });

    foreach (['shipped', 'pending', 'paid', 'cancelled'] as $status) {
        expect($summaries['groups'][$status])->toBe(['total_cents' => [['label' => 'Revenue', 'value' => groupCents((int) DB::table('orders')->where('status', $status)->sum('total_cents'))]]]);
    }

    expect($summaries['groups'])->toHaveCount(4)
        ->and(array_filter($queries, fn (array $query) => str_contains($query['sql'], 'group by')))->toHaveCount(1);
});

it('limits the subtotal query to the group keys on the page', function () {
    $this->seed(DemoSeeder::class);
    actAsGroupAdmin();

    $queries = [];
    $summaries = null;
    $queries = groupQueriesDuring(function () use (&$summaries) {
        $summaries = $this->get('/lathe/resources/orders?group=status:desc', groupPartial())->assertOk()->json('props.summaries');
    });
    $grouped = array_values(array_filter($queries, fn (array $query) => str_contains($query['sql'], 'group by')));

    expect(array_keys($summaries['groups']))->toBe(['shipped'])
        ->and($summaries['groups']['shipped']['total_cents'][0]['value'])->toBe(groupCents((int) DB::table('orders')->where('status', 'shipped')->sum('total_cents')))
        ->and($grouped)->toHaveCount(1)
        ->and($grouped[0]['bindings'])->toBe(['shipped']);
});

it('lets group=_none override the default group', function () {
    app(DemoSeeder::class)->forOrders(40)->run();

    $default = new ResourceQuery(DefaultGroupedOrders::make(), groupRequest([]));
    $none = new ResourceQuery(DefaultGroupedOrders::make(), groupRequest(['group' => '_none']));

    expect($default->activeGroup()['group']->id())->toBe('status')
        ->and($default->rows()->first()?->getAttribute('status'))->toBe('cancelled')
        ->and($none->activeGroup())->toBeNull()
        ->and($none->rows()->first()?->getKey())->toBe(DB::table('orders')->max('id'));
});

it('answers 422 for an unknown group id or direction', function (string $group) {
    app(DemoSeeder::class)->forOrders(40)->run();
    actAsGroupAdmin();

    $this->getJson('/lathe/api/resources/orders?'.$group)->assertUnprocessable()->assertJsonValidationErrors(['group']);
})->with(['group=nope', 'group=status:up', 'group=status:desc:asc', 'group[]=status']);

it('puts a null key under __lathe_null__ on the rows and the subtotals', function () {
    groupThings();

    ['payload' => $payload, 'summaries' => $summaries] = groupPayload(GroupThings::class, ['group' => 'tier']);

    expect(collect($payload['table']['rows'])->pluck('group.key')->sort()->values()->all())->toBe(['__lathe_null__', '__lathe_null__', 'gold', 'gold'])
        ->and($summaries['groups'])->toEqual([
            '__lathe_null__' => ['quantity' => [['label' => 'Sum', 'value' => '6']]],
            'gold' => ['quantity' => [['label' => 'Sum', 'value' => '9']]],
        ]);
});

it('buckets a date column by day', function () {
    groupThings();

    ['payload' => $payload, 'summaries' => $summaries] = groupPayload(GroupThings::class, ['group' => 'day:desc']);

    expect(array_column(array_column($payload['table']['rows'], 'group'), 'key'))->toBe(['2026-03-09', '2026-03-09', '2026-03-08', '2026-03-08'])
        ->and($summaries['groups'])->toEqual([
            '2026-03-09' => ['quantity' => [['label' => 'Sum', 'value' => '12']]],
            '2026-03-08' => ['quantity' => [['label' => 'Sum', 'value' => '3']]],
        ]);
});

it('groups a datetime column by the user\'s local day', function (string $zone, array $keys, array $totals) {
    Lathe::userTimezone(fn () => $zone);
    $customer = Customer::create(['name' => 'Ann', 'email' => 'ann@example.com']);
    UserFactory::new()->create(['email' => 'admin@lathephp.com']);

    foreach ([['ORD-1', '2026-03-08 23:30:00', 100], ['ORD-2', '2026-03-08 03:00:00', 20], ['ORD-3', '2026-03-09 05:00:00', 3]] as [$number, $placed, $cents]) {
        Order::create(['customer_id' => $customer->id, 'number' => $number, 'status' => 'paid', 'total_cents' => $cents, 'placed_at' => $placed]);
    }

    actAsGroupAdmin();
    $rows = $this->getJson('/lathe/api/resources/orders?group=placed&sort=number')->assertOk()->json('table.rows');
    $summaries = $this->get('/lathe/resources/orders?group=placed&sort=number', groupPartial())->assertOk()->json('props.summaries');

    expect(array_column(array_column($rows, 'group'), 'key'))->toBe($keys)
        ->and(array_map(fn (array $group) => $group['total_cents'][0]['value'], $summaries['groups']))->toBe($totals);
})->with([
    'UTC' => ['UTC', ['2026-03-08', '2026-03-08', '2026-03-09'], ['2026-03-08' => '$1.20', '2026-03-09' => '$0.03']],
    'Pacific/Kiritimati' => ['Pacific/Kiritimati', ['2026-03-08', '2026-03-09', '2026-03-09'], ['2026-03-08' => '$0.20', '2026-03-09' => '$1.03']],
    'America/New_York' => ['America/New_York', ['2026-03-07', '2026-03-08', '2026-03-09'], ['2026-03-07' => '$0.20', '2026-03-08' => '$1.00', '2026-03-09' => '$0.03']],
]);

it('joins a to-one relation group like a relation sort', function () {
    app(DemoSeeder::class)->forOrders(40)->run();

    $query = new ResourceQuery(CountryGroupedOrders::make(), groupRequest(['group' => 'customer.country']));
    $rows = null;
    $queries = groupQueriesDuring(function () use ($query, &$rows) {
        $rows = $query->rows();
    });
    $sql = $queries[0]['sql'];
    $group = $query->activeGroup()['group'];
    $expected = DB::table('orders')->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
        ->orderBy('customers.country')->orderByDesc('orders.id')->limit(25)->pluck('orders.id')->all();

    expect($sql)->toContain('select orders.* from orders left join customers on orders.customer_id = customers.id')
        ->and($sql)->toContain('order by customers.country asc, orders.id desc')
        ->and($rows->modelKeys())->toBe($expected)
        ->and($group->keyFor($rows->first()))->toBe(Customer::findOrFail($rows->first()->getAttribute('customer_id'))->country);
});

it('fails the manifest naming the Resource for a group on a to-many relation', function () {
    app(ResourceRegistry::class)->register(ItemGroupedOrders::class);

    expect(fn () => Lathe::resources()->manifest())->toThrow(InvalidConfigurationException::class, ItemGroupedOrders::class);
});
