<?php

namespace Lathe\Tests\Feature\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/**
 * An order list whose index query joins a table that also has a status column.
 */
class JoinedOrder extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->title('number');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Number')->sortable(),
            Field::make('Status')->sortable(),
            Field::make('Items', 'items.number')->sortable(),
        ];
    }

    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->join('order_flags', 'order_flags.order_id', '=', 'orders.id')->select('orders.*');
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn (User $user) => $user->email === 'admin@lathephp.com');
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/**
 * The SQL of the row query a request ran, with identifier quotes removed.
 */
function sortSqlDuring(\Closure $request): string
{
    $sql = [];
    DB::listen(function ($event) use (&$sql) {
        $sql[] = str_replace(['"', '`'], '', $event->sql);
    });
    $request();

    $rows = array_values(array_filter($sql, fn (string $query) => str_contains($query, 'order by') && str_contains($query, ' limit ')));

    return $rows[0] ?? '';
}

it('sorts the largest totals first', function () {
    $rows = $this->getJson('/lathe/api/resources/orders?sort=-total_cents&per_page=50')->assertOk()->json('table.rows');
    $values = array_map(fn (array $row) => (float) $row['cells']['total_cents']['value'], $rows);

    $expected = $values;
    rsort($expected);

    expect($values)->toBe($expected)
        ->and($values[0])->toBe(Order::max('total_cents') / 100);
});

it('sorts by two keys', function () {
    $rows = $this->getJson('/lathe/api/resources/orders?sort=-total_cents,placed_at&per_page=100')->assertOk()->json('table.rows');

    $expected = Order::query()->orderBy('total_cents', 'desc')->orderBy('placed_at')->orderBy('id')->limit(100)->pluck('id')->all();

    expect(array_column($rows, 'id'))->toBe($expected);
});

it('takes 3 tokens and refuses 4', function () {
    $this->getJson('/lathe/api/resources/orders?sort=number,total_cents,placed_at')->assertOk();
    $this->getJson('/lathe/api/resources/orders?sort=number,total_cents,placed_at,customer.name')->assertUnprocessable();
});

it('refuses a key that is not a sortable field', function (string $sort) {
    $this->getJson('/lathe/api/resources/orders?sort='.$sort)
        ->assertUnprocessable()
        ->assertJsonPath('errors.sort.0', 'sort is invalid');
})->with(['password', 'status', 'id', '-', ',', 'total_cents,,placed_at', 'total_cents:asc:desc', '-total_cents:asc']);

it('refuses a sort that is not a string', function () {
    $this->getJson('/lathe/api/resources/orders?sort[]=total_cents')->assertUnprocessable()->assertJsonValidationErrors(['sort']);
});

it('sort_direction_accepts_only_asc_and_desc', function () {
    $this->getJson('/lathe/api/resources/orders?sort=total_cents:up')->assertUnprocessable()->assertJsonValidationErrors(['sort']);
    $this->getJson('/lathe/api/resources/orders?sort=total_cents:desc')->assertOk();
    $this->getJson('/lathe/api/resources/orders?sort=total_cents:asc')->assertOk();
})->group('regression');

it('qualifies and de-duplicates the columns and ends on the key in the last direction', function (string $sort, string $orderBy) {
    $sql = sortSqlDuring(fn () => $this->getJson('/lathe/api/resources/orders?sort='.$sort)->assertOk());

    expect($sql)->toContain($orderBy);
})->with([
    ['-total_cents,placed_at', 'order by orders.total_cents desc, orders.placed_at asc, orders.id asc'],
    ['total_cents,placed_at,-total_cents', 'order by orders.total_cents asc, orders.placed_at asc, orders.id desc'],
    ['total_cents:desc', 'order by orders.total_cents desc, orders.id desc'],
]);

it('keeps the key descending when there is no sort', function () {
    $sql = sortSqlDuring(fn () => $this->getJson('/lathe/api/resources/orders')->assertOk());

    expect($sql)->toContain('order by orders.id desc');
});

it('sort_columns_are_table_qualified_on_joined_query', function () {
    Schema::create('order_flags', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->string('status');
    });
    DB::table('order_flags')->insert(Order::query()->limit(5)->pluck('id')->map(fn ($id) => ['order_id' => $id, 'status' => 'flagged'])->all());

    $query = new ResourceQuery(JoinedOrder::make(), LatheRequest::createFrom(Request::create('/x?sort=status,-number')));
    $query->validate();

    DB::enableQueryLog();
    $rows = $query->rows();
    $log = str_replace(['"', '`'], '', DB::getQueryLog()[0]['query']);

    expect($rows)->toHaveCount(5)
        ->and($log)->toContain('order by orders.status asc, orders.number desc, orders.id desc');
})->group('regression');

it('sorts a belongsTo field through a left join and selects the model columns', function () {
    $sql = sortSqlDuring(fn () => $this->getJson('/lathe/api/resources/orders?sort=customer.name')->assertOk());

    expect($sql)->toContain('select orders.* from orders left join customers on orders.customer_id = customers.id')
        ->and($sql)->toContain('order by customers.name asc, orders.id asc');

    $rows = $this->getJson('/lathe/api/resources/orders?sort=customer.name&per_page=50')->json('table.rows');

    expect($rows[0]['cells']['customer']['display'])->toBe(DB::table('customers')->orderBy('name')->value('name'));
});

it('marks the sortable columns and refuses a computed field', function () {
    $json = $this->getJson('/lathe/api/resources/orders')->assertOk()->json();

    expect(array_column($json['table']['columns'], 'sortable', 'label'))
        ->toBe(['Number' => true, 'Customer' => true, 'Status' => false, 'Total' => true, 'Placed' => true])
        ->and($json['table']['sortKeys'])->toBe(['number', 'customer.name', 'total_cents', 'placed_at'])
        ->and($json['state']['sort'])->toBe('');

    $this->getJson('/lathe/api/resources/orders?sort=status')->assertUnprocessable();
});

it('refuses a sortable to-many field and leaves it out of the sort keys', function () {
    $request = LatheRequest::createFrom(Request::create('/x?sort=items.number'));

    expect(fn () => (new ResourceQuery(JoinedOrder::make(), $request))->validate())->toThrow(ValidationException::class)
        ->and((new ResourceQuery(JoinedOrder::make(), LatheRequest::createFrom(Request::create('/x'))))->sortKeys())->toBe(['number', 'status']);
});

it('echoes the sort in the state', function () {
    $this->getJson('/lathe/api/resources/orders?sort=-total_cents,placed_at:asc')->assertOk()->assertJsonPath('state.sort', '-total_cents,placed_at');
});

it('answers 422 on the page for a bad sort', function () {
    $this->get('/lathe/resources/orders?sort=password')->assertUnprocessable();
});
