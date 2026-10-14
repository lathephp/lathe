<?php

use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Resources\ResourceRegistry;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(RelationIndexOrders::class, RelationIndexItems::class, RelationIndexSecretOrders::class, RelationIndexPrivateOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

/** Orders whose Items panel offers 10 or 25 rows a page and lists RelationIndexItems. */
class RelationIndexOrders extends Resource
{
    public static function uriKey(): string
    {
        return 'relation-index-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Number'),
            Field::hasMany('Items')->resource(RelationIndexItems::class)->perPageOptions([10, 25]),
        ];
    }
}

/** Order items with a filterable Quantity. */
class RelationIndexItems extends Resource
{
    public static function uriKey(): string
    {
        return 'relation-index-items';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderItem::class)->canonical(false)->title('id')->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::belongsTo('Order'),
            Field::number('Quantity')->sortable()->filterable(),
        ];
    }
}

/** Orders whose Items panel no one may see. */
class RelationIndexSecretOrders extends RelationIndexOrders
{
    public static function uriKey(): string
    {
        return 'relation-index-secret-orders';
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::hasMany('Items')->resource(RelationIndexItems::class)->canSee(fn () => false)];
    }
}

/** Lists every order but lets no one view one. */
class RelationIndexNoViewPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return false;
    }
}

class RelationIndexPrivateOrders extends Resource
{
    public static ?string $policy = RelationIndexNoViewPolicy::class;

    public static function uriKey(): string
    {
        return 'relation-index-private-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false);
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::hasMany('Items')->resource(RelationIndexItems::class)];
    }
}

/**
 * Give order 12 exactly $count items, each with product 1 and its line number as quantity.
 */
function relationIndexItems(int $count): void
{
    DB::table('order_items')->where('order_id', 12)->delete();

    foreach (range(1, $count) as $line) {
        DB::table('order_items')->insert(['order_id' => 12, 'product_id' => 1, 'quantity' => $line, 'price_cents' => 100]);
    }
}

it('lists the items of order 12 with their columns, related ids and a count', function () {
    $ids = OrderItem::where('order_id', 12)->orderBy('id')->pluck('id')->all();

    $json = $this->getJson('/lathe/api/resources/orders/12/items')->assertOk()->json();
    $rows = collect($json['rows']);

    expect($rows->pluck('related_id')->sort()->values()->all())->toBe($ids)
        ->and($rows->every(fn (array $row) => $row['related_id'] === $row['id']))->toBeTrue()
        ->and(collect($json['columns'])->pluck('key')->all())->toBe(['product', 'quantity'])
        ->and(array_keys($rows->first()['cells']))->toBe(['product', 'quantity'])
        ->and($json['count'])->toBe(['value' => count($ids), 'exact' => false, 'capped' => false, 'estimated' => false]);
});

it('sorts the items by quantity descending', function () {
    relationIndexItems(4);

    $json = $this->getJson('/lathe/api/resources/orders/12/items?sort=-quantity')->assertOk()->json();

    expect(array_column(array_column(array_column($json['rows'], 'cells'), 'quantity'), 'value'))->toBe([4, 3, 2, 1]);
});

it('takes the search, page, page size and filters of the related Resource', function () {
    relationIndexItems(30);

    $json = $this->getJson('/lathe/api/resources/relation-index-orders/12/items?filter[field_quantity][min]=5&sort=quantity&per_page=10&page=2&q=')->assertOk()->json();

    expect(array_column(array_column(array_column($json['rows'], 'cells'), 'quantity'), 'value'))->toBe(range(15, 24))
        ->and($json['page'])->toBe(2)
        ->and($json['perPage'])->toBe(10)
        ->and($json['count']['value'])->toBe(26);

    $id = OrderItem::where('order_id', 12)->where('quantity', 7)->value('id');
    $found = $this->getJson("/lathe/api/resources/orders/12/items?q={$id}")->assertOk()->json('rows');

    expect(array_column($found, 'related_id'))->toContain($id);
});

it('answers 404 for a parent outside the scope and for an unknown relationship', function () {
    $this->getJson('/lathe/api/resources/orders/99999999/items')->assertNotFound();
    $this->getJson('/lathe/api/resources/orders/12/nothing')->assertNotFound();
});

it('answers 403 for a relation field the user may not see', function () {
    $this->getJson('/lathe/api/resources/relation-index-secret-orders/12/items')->assertForbidden();
});

it('answers 403 when the user may not view the parent', function () {
    $this->getJson('/lathe/api/resources/relation-index-private-orders/12/items')->assertForbidden();
});

test('relation_sort_rejects_unknown_column', function () {
    $this->getJson('/lathe/api/resources/orders/12/items?sort=password')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort');
})->group('regression');

it('runs the same queries for 10 and 100 rows, reads orders once and asks for abilities once', function () {
    $authorization = new class(app(GateContract::class), app(ResourceRegistry::class)) extends ResourceAuthorization
    {
        public int $batches = 0;

        public function abilitiesFor(?Authenticatable $user, string $resource, Collection $models, array $abilities): array
        {
            $this->batches++;

            return parent::abilitiesFor($user, $resource, $models, $abilities);
        }
    };
    app()->instance(ResourceAuthorization::class, $authorization);
    Model::preventLazyLoading();

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $run = function (int $rows) use (&$queries) {
        relationIndexItems($rows);
        $queries = [];
        $json = $this->getJson('/lathe/api/resources/orders/12/items?per_page=100')->assertOk()->json();

        expect($json['rows'])->toHaveCount($rows);

        return $queries;
    };

    $ten = $run(10);
    $hundred = $run(100);
    Model::preventLazyLoading(false);

    $onOrders = array_values(array_filter($hundred, fn (string $sql) => preg_match('/["`\s]orders["`\s]/', $sql) === 1));

    expect(count($hundred))->toBe(count($ten))
        ->and($onOrders)->toHaveCount(1)
        ->and($onOrders[0])->toContain('where')
        ->and($authorization->batches)->toBe(2);
});

it('falls back to the first page size and defaults to the field\'s first option', function () {
    expect($this->getJson('/lathe/api/resources/orders/12/items?per_page=101')->assertOk()->json('perPage'))->toBe(25)
        ->and($this->getJson('/lathe/api/resources/relation-index-orders/12/items')->assertOk()->json('perPage'))->toBe(10)
        ->and($this->getJson('/lathe/api/resources/relation-index-orders/12/items')->json('perPageOptions'))->toBe([10, 25]);
});

it('lists the tags of product 2 with their pivot id and position', function () {
    $pivots = DB::table('taggables')->where('taggable_type', 'Workbench\App\Models\Product')->where('taggable_id', 2)->orderBy('position')->get();

    $json = $this->getJson('/lathe/api/resources/products/2/tags?sort=position')->assertOk()->json();

    expect(array_column($json['rows'], 'related_id'))->toBe($pivots->pluck('tag_id')->all())
        ->and(array_column($json['rows'], 'pivot_id'))->toBe($pivots->pluck('id')->all())
        ->and(array_column(array_column(array_column($json['rows'], 'cells'), 'position'), 'value'))->toBe([0, 1])
        ->and(collect($json['columns'])->pluck('key')->all())->toContain('position');

    $reversed = $this->getJson('/lathe/api/resources/products/2/tags?sort=-position')->assertOk()->json('rows');

    expect(array_column(array_column(array_column($reversed, 'cells'), 'position'), 'value'))->toBe([1, 0]);

    $this->getJson('/lathe/api/resources/products/2/tags?sort=nope')->assertUnprocessable()->assertJsonValidationErrors('sort');
});
