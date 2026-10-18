<?php

namespace Lathe\Tests\Feature\Actions\Menu;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Lathe\Actions\Action;
use Lathe\Actions\DestructiveAction;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\ResourceDefinition;
use RuntimeException;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Models\Order as OrderModel;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

/** Orders under another key, with the actions a test declares. */
class MenuOrders extends Order
{
    /** @var list<Action> */
    public static array $declared = [];

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function actions(LatheRequest $request): array
    {
        return static::$declared;
    }
}

class ExplodingFields extends Action
{
    public function fields(LatheRequest $request): array
    {
        throw new RuntimeException('The list resolved the fields.');
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Lathe::registerResources(MenuOrders::class);
    MenuOrders::$declared = [];
    actingAsStaff('admin@lathephp.com');
});

function actingAsStaff(string $email): void
{
    test()->actingAs(User::where('email', $email)->firstOrFail());
}

/**
 * The SQL a request ran.
 *
 * @return list<string>
 */
function sqlDuring(callable $request): array
{
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        $queries[] = $event->sql;
    });

    $request();

    return $queries;
}

it('lists the Orders actions in menu order with their metadata', function () {
    $json = $this->getJson('/lathe/api/resources/orders/actions?display=index')->assertOk()->json();

    expect(array_keys($json))->toBe(['actions', 'pivotActions', 'counts'])
        ->and(array_column($json['actions'], 'uriKey'))->toBe(['export', 'hold-order', 'mark-shipped', 'cancel-order'])
        ->and($json['actions'][0])->toMatchArray(['name' => 'Export', 'group' => null, 'standalone' => false])
        ->and($json['actions'][2])->toMatchArray(['name' => 'Mark shipped', 'group' => 'Fulfilment', 'destructive' => false, 'confirmText' => 'Mark :count orders as shipped?', 'confirmButtonText' => 'Mark shipped'])
        ->and($json['actions'][3])->toMatchArray(['name' => 'Cancel order', 'destructive' => true, 'confirmText' => 'Cancel :count order?'])
        ->and($json['actions'][1])->toMatchArray(['name' => 'Hold order', 'sole' => true, 'showOnTableRow' => true])
        ->and($json['actions'][1])->not->toHaveKey('authorizedToRun')
        ->and($json['pivotActions'])->toBe([])
        ->and($json['counts'])->toEqual(['standalone' => 0, 'sole' => 1, 'resource' => 3]);
});

it('lists an action without resolving its fields', function () {
    MenuOrders::$declared = [ExplodingFields::make(), Action::make()->withName('Export')->withUriKey('export')->standalone()];

    $json = $this->getJson('/lathe/api/resources/menu-orders/actions?display=index')->assertOk()->json();

    expect(array_column($json['actions'], 'uriKey'))->toBe(['export', 'exploding-fields'])
        ->and($json['counts'])->toEqual(['standalone' => 1, 'sole' => 0, 'resource' => 1]);
});

it('refuses an invalid display', function (string $query) {
    $this->getJson("/lathe/api/resources/orders/actions{$query}")->assertUnprocessable()->assertJsonValidationErrors('display');
})->with(['?display=sidebar', '']);

it('hides an action canSee refuses and answers 403 for its form', function () {
    MenuOrders::$declared = [Action::make()->withName('Secret')->withUriKey('secret')->canSee(fn () => false), Action::make()->withName('Open')->withUriKey('open')];

    $json = $this->getJson('/lathe/api/resources/menu-orders/actions?display=index')->assertOk()->json();

    expect(array_column($json['actions'], 'uriKey'))->toBe(['open']);
    $this->getJson('/lathe/api/resources/menu-orders/actions/secret/form')->assertForbidden();
    $this->getJson('/lathe/api/resources/menu-orders/actions/nowhere/form')->assertNotFound();
});

it('resolves the Mark Shipped form with the count in its confirm text and no orders query', function () {
    $sql = sqlDuring(function () use (&$json) {
        $json = $this->getJson('/lathe/api/resources/orders/actions/mark-shipped/form?count=3')->assertOk()->json();
    });

    expect($json['fields'])->toBe([])
        ->and($json['confirmText'])->toBe('Mark 3 orders as shipped?')
        ->and($json['action'])->toMatchArray(['uriKey' => 'mark-shipped', 'group' => 'Fulfilment'])
        ->and(array_values(array_filter($sql, fn (string $query) => str_contains($query, 'orders'))))->toBe([]);
});

it('sends the action fields with their defaults on the form', function () {
    MenuOrders::$declared = [(new class extends Action
    {
        public function fields(LatheRequest $request): array
        {
            return [Field::text('Reason')->default('late'), Field::text('Secret')->canSee(fn () => false)];
        }
    })->withName('Note')->withUriKey('note')];

    $json = $this->getJson('/lathe/api/resources/menu-orders/actions/note/form')->assertOk()->json();

    expect(array_column($json['fields'], 'attribute'))->toBe(['reason'])
        ->and($json['fields'][0]['value'])->toBe('late')
        ->and($json['confirmText'])->toBeNull();
});

it('carries the action menu and row flags on the index page and JSON', function () {
    $json = $this->getJson('/lathe/api/resources/orders')->assertOk()->json();

    expect(array_column($json['actions']['actions'], 'uriKey'))->toBe(['export', 'hold-order', 'mark-shipped', 'cancel-order'])
        ->and($json['table']['rows'][0]['actions'])->toBe(['export', 'hold-order', 'mark-shipped', 'cancel-order']);

    $this->get('/lathe/resources/orders')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Resources/Index', shouldExist: false)
            ->where('actions.actions.2.uriKey', 'mark-shipped')
            ->where('table.rows.0.actions', ['export', 'hold-order', 'mark-shipped', 'cancel-order'])
            ->etc());
});

it('leaves out the actions an editor may not run from each row', function () {
    actingAsStaff('editor@lathephp.com');

    $rows = $this->getJson('/lathe/api/resources/orders')->assertOk()->json('table.rows');

    // The editor may export orders but run none of their other actions.
    expect($rows[0]['actions'])->toBe(['export']);
});

it('flags rows by run callback and policy, and skips standalone and hidden actions', function () {
    MenuOrders::$declared = [
        Action::make()->withName('Even')->withUriKey('even')->canRunWhen(fn (OrderModel $order) => $order->id % 2 === 0),
        Action::make()->withName('Export')->withUriKey('export')->standalone(),
        Action::make()->withName('Secret')->withUriKey('secret')->canSee(fn () => false),
        DestructiveAction::make()->withName('Purge')->withUriKey('purge'),
    ];
    actingAsStaff('editor@lathephp.com');

    $rows = collect($this->getJson('/lathe/api/resources/menu-orders')->assertOk()->json('table.rows'));
    $even = $rows->filter(fn (array $row) => $row['id'] % 2 === 0);
    $odd = $rows->reject(fn (array $row) => $row['id'] % 2 === 0);

    expect($even)->not->toBeEmpty()
        ->and($odd)->not->toBeEmpty()
        ->and($even->pluck('actions')->unique()->values()->all())->toBe([['even']])
        ->and($odd->pluck('actions')->unique()->values()->all())->toBe([[]]);
});

it('gives the lens index the same action menu', function () {
    $json = $this->getJson('/lathe/api/resources/orders/lenses/pending-orders')->assertOk()->json();

    expect(array_column($json['actions']['actions'], 'uriKey'))->toBe(['export', 'hold-order', 'mark-shipped', 'cancel-order'])
        ->and($json['table']['rows'][0]['actions'])->toBe(['export', 'hold-order', 'mark-shipped', 'cancel-order']);
});

it('runs the same queries for 10 and 100 rows with 4 actions declared', function () {
    config(['lathe.pagination.per_page_options' => [10, 25, 50, 100]]);
    MenuOrders::$declared = [
        Action::make()->withName('Ship')->withUriKey('ship'),
        Action::make()->withName('Even')->withUriKey('even')->canRun(fn (LatheRequest $request, OrderModel $order) => $order->id % 2 === 0),
        DestructiveAction::make()->withName('Purge')->withUriKey('purge'),
        Action::make()->withName('Export')->withUriKey('export')->standalone(),
    ];

    $ten = sqlDuring(fn () => $this->getJson('/lathe/api/resources/menu-orders?per_page=10')->assertOk()->assertJsonPath('table.rows.0.actions.0', 'ship'));
    $hundred = sqlDuring(fn () => $this->getJson('/lathe/api/resources/menu-orders?per_page=100')->assertOk()->assertJsonPath('table.rows.99.actions.0', 'ship'));

    expect(count($hundred))->toBe(count($ten));
});

it('resolves authorizedToRun for one model on the detail display', function () {
    MenuOrders::$declared = [
        Action::make()->withName('Twelve')->withUriKey('twelve')->canRunWhen(fn (OrderModel $order) => $order->id === 12),
        Action::make()->withName('Ship')->withUriKey('ship'),
        DestructiveAction::make()->withName('Purge')->withUriKey('purge'),
        Action::make()->withName('Export')->withUriKey('export')->standalone(),
    ];

    $flags = fn (string $query) => collect($this->getJson("/lathe/api/resources/menu-orders/actions?display=detail&{$query}")->assertOk()->json('actions'))
        ->pluck('authorizedToRun', 'uriKey')->all();

    expect($flags('resourceId=12'))->toBe(['export' => true, 'twelve' => true, 'ship' => true, 'purge' => true])
        ->and($flags('resourceId=13'))->toBe(['export' => true, 'twelve' => false, 'ship' => true, 'purge' => true]);

    actingAsStaff('editor@lathephp.com');

    expect($flags('resourceId=12'))->toBe(['export' => true, 'twelve' => true, 'ship' => false, 'purge' => false]);
    $this->getJson('/lathe/api/resources/menu-orders/actions?display=detail&resourceId=99999999')->assertNotFound();
});
