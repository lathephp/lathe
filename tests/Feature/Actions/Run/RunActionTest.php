<?php

namespace Lathe\Tests\Feature\Actions\Run;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\StringThing as StringThingModel;
use Lathe\Tests\Fixtures\Models\UlidThing as UlidThingModel;
use Lathe\Tests\Fixtures\Models\UuidThing as UuidThingModel;
use Lathe\Tests\Fixtures\Resources\StringThing;
use Lathe\Tests\Fixtures\Resources\UlidThing;
use Lathe\Tests\Fixtures\Resources\UuidThing;
use RuntimeException;
use Throwable;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class RunDeclared
{
    /** @var list<Action> */
    public static array $actions = [];

    /** @var list<string> */
    public static array $calls = [];
}

class RunOrders extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false)->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public function actions(LatheRequest $request): array
    {
        return RunDeclared::$actions;
    }
}

class RunOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function runAction(User $user, Order $order, Action $action): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function runDestructiveAction(User $user, Order $order, Action $action): bool
    {
        return false;
    }
}

class RunGuardedOrders extends RunOrders
{
    public static ?string $policy = RunOrderPolicy::class;

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Order::class)->canonical(false);
    }
}

class RunUuidThings extends UuidThing
{
    public function actions(LatheRequest $request): array
    {
        return RunDeclared::$actions;
    }
}

class RunUlidThings extends UlidThing
{
    public function actions(LatheRequest $request): array
    {
        return RunDeclared::$actions;
    }
}

class RunStringThings extends StringThing
{
    public function actions(LatheRequest $request): array
    {
        return RunDeclared::$actions;
    }
}

class RunHandles extends Action
{
    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        RunDeclared::$calls[] = 'handle';

        throw new RuntimeException('boom');
    }

    public function failed(Throwable $e, Model $model): void
    {
        RunDeclared::$calls[] = "failed:{$e->getMessage()}:{$model->getKey()}";
    }
}

class RunHandlesOrder extends RunHandles
{
    /**
     * @param  Collection<int, Order>  $models
     */
    public function handleForOrder(ActionFields $fields, Collection $models): void
    {
        RunDeclared::$calls[] = 'handleForOrder';

        throw new RuntimeException('boom');
    }

    public function failedForOrder(Throwable $e, Model $model): void
    {
        RunDeclared::$calls[] = "failedForOrder:{$e->getMessage()}:{$model->getKey()}";
    }
}

class RunHandlesOrders extends RunHandlesOrder
{
    /**
     * @param  Collection<int, Order>  $models
     */
    public function handleForOrders(ActionFields $fields, Collection $models): void
    {
        RunDeclared::$calls[] = 'handleForOrders';

        throw new RuntimeException('boom');
    }

    public function failedForOrders(Throwable $e, Model $model): void
    {
        RunDeclared::$calls[] = "failedForOrders:{$e->getMessage()}:{$model->getKey()}";
    }
}

/**
 * An action that sets each row's status, and throws after the write for the listed keys.
 *
 * @param  list<int>  $throwOn
 */
function runShipping(array $throwOn = []): Action
{
    return Action::make()->withName('Mark Shipped')->withUriKey('mark-shipped')->handleUsing(function (ActionFields $fields, Collection $models) use ($throwOn) {
        foreach ($models as $order) {
            RunDeclared::$calls[] = 'handle:'.implode(',', $models->map(fn (Model $model) => $model->getKey())->all());
            $order->update(['status' => 'shipped']);

            if (in_array($order->getKey(), $throwOn, true)) {
                throw new RuntimeException("Order {$order->getKey()} is on hold.");
            }
        }
    });
}

/**
 * @param  list<int|string>  $ids
 * @return array<string, mixed>
 */
function runBody(string $action, array $ids): array
{
    return ['action' => $action, 'selection' => ['mode' => 'ids', 'ids' => $ids], 'fields' => []];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    RunDeclared::$actions = [];
    RunDeclared::$calls = [];
    Lathe::registerResources(RunOrders::class, RunGuardedOrders::class);
    Order::whereKey(range(1, 60))->update(['status' => 'pending']);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('bulk_action_runs_handler_once_per_model', function () {
    $thenCalls = [];
    RunDeclared::$actions = [runShipping()->then(function (Collection $models) use (&$thenCalls) {
        $thenCalls[] = $models->map(fn (Model $model) => $model->getKey())->all();
    })];

    $this->postJson('/lathe/api/resources/run-orders/action', runBody('mark-shipped', [1, 2]))
        ->assertOk()
        ->assertExactJson([
            'result' => ['message' => 'Action was executed successfully.', 'level' => 'success', 'effect' => null],
            'permitted' => 2,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ]);

    expect(RunDeclared::$calls)->toBe(['handle:1', 'handle:2'])
        ->and(Order::whereKey([1, 2])->pluck('status')->all())->toBe(['shipped', 'shipped'])
        ->and($thenCalls)->toBe([[1, 2]]);
})->group('regression');

it('rolls back each failing row and counts it, while the other rows keep their change', function () {
    $thenCalls = 0;
    RunDeclared::$actions = [runShipping([3, 6, 9])->then(function () use (&$thenCalls) {
        $thenCalls++;
    })];

    $response = $this->postJson('/lathe/api/resources/run-orders/action', runBody('mark-shipped', range(1, 10)))
        ->assertOk();

    expect($response->json('result'))->toBe(['message' => '7 succeeded, 3 failed.', 'level' => 'success', 'effect' => null])
        ->and($response->json('permitted'))->toBe(10)
        ->and($response->json('skipped'))->toBe(0)
        ->and($response->json('failed'))->toBe(3)
        ->and($response->json('errors'))->toBe([
            ['id' => 3, 'message' => 'Order 3 is on hold.'],
            ['id' => 6, 'message' => 'Order 6 is on hold.'],
            ['id' => 9, 'message' => 'Order 9 is on hold.'],
        ])
        ->and(Order::whereKey(range(1, 10))->orderBy('id')->pluck('status', 'id')->all())->toBe([
            1 => 'shipped', 2 => 'shipped', 3 => 'pending', 4 => 'shipped', 5 => 'shipped',
            6 => 'pending', 7 => 'shipped', 8 => 'shipped', 9 => 'pending', 10 => 'shipped',
        ])
        ->and($thenCalls)->toBe(0);
});

it('answers a danger toast when every row fails', function () {
    RunDeclared::$actions = [runShipping([1, 2])];

    $this->postJson('/lathe/api/resources/run-orders/action', runBody('mark-shipped', [1, 2]))
        ->assertOk()
        ->assertJsonPath('result', ['message' => 'Action failed for all selected resources.', 'level' => 'danger', 'effect' => null])
        ->assertJsonPath('failed', 2);
});

it('keeps at most 50 errors for 60 failing rows', function () {
    RunDeclared::$actions = [runShipping(range(1, 60))];

    $response = $this->postJson('/lathe/api/resources/run-orders/action', runBody('mark-shipped', range(1, 60)))->assertOk();

    expect($response->json('failed'))->toBe(60)
        ->and($response->json('errors'))->toHaveCount(50)
        ->and($response->json('errors.49'))->toBe(['id' => 50, 'message' => 'Order 50 is on hold.']);
});

it('runs only the rows canRunWhen allows and counts the rest as skipped', function () {
    Order::whereKey([6, 7, 8])->update(['status' => 'shipped']);
    RunDeclared::$actions = [Action::make()->withName('Deliver')->withUriKey('deliver')
        ->canRunWhen(fn (Order $order) => $order->status === 'pending')
        ->handleUsing(fn (ActionFields $fields, Collection $models) => $models->each(fn (Order $order) => $order->update(['status' => 'delivered'])))];

    $this->postJson('/lathe/api/resources/run-orders/action', runBody('deliver', range(1, 8)))
        ->assertOk()
        ->assertJsonPath('permitted', 5)
        ->assertJsonPath('skipped', 3);

    expect(Order::whereKey(range(1, 8))->orderBy('id')->pluck('status')->all())
        ->toBe(['delivered', 'delivered', 'delivered', 'delivered', 'delivered', 'shipped', 'shipped', 'shipped']);
});

it('runs on UUID, ULID and string keys', function (string $resource, string $table, string $model, string $column, array $keys) {
    Schema::create($table, function (Blueprint $table) use ($column) {
        $table->{$column}('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
    Lathe::registerResources($resource);
    $model::forceCreate(['id' => $keys[0], 'name' => 'pending']);
    $model::forceCreate(['id' => $keys[1], 'name' => 'pending']);
    $model::forceCreate(['id' => $keys[2], 'name' => 'shipped']);
    RunDeclared::$actions = [Action::make()->withName('Deliver')->withUriKey('deliver')
        ->canRunWhen(fn (Model $thing) => $thing->getAttribute('name') === 'pending')
        ->handleUsing(fn (ActionFields $fields, Collection $models) => $models->each(fn (Model $thing) => $thing->update(['name' => 'delivered'])))];

    $this->postJson('/lathe/api/resources/'.$resource::uriKey().'/action', runBody('deliver', $keys))
        ->assertOk()
        ->assertJsonPath('permitted', 2)
        ->assertJsonPath('skipped', 1);

    expect($model::whereKey($keys)->pluck('name', 'id')->all())
        ->toEqual([$keys[0] => 'delivered', $keys[1] => 'delivered', $keys[2] => 'shipped']);
})->with([
    'uuid' => [RunUuidThings::class, 'uuid_things', UuidThingModel::class, 'uuid', ['9f1c2b3a-5d4e-4f60-8a7b-1c2d3e4f5a6b', '9f1c2b3b-5d4e-4f60-8a7b-1c2d3e4f5a6b', '9f1c2b3c-5d4e-4f60-8a7b-1c2d3e4f5a6b']],
    'ulid' => [RunUlidThings::class, 'ulid_things', UlidThingModel::class, 'ulid', ['01j9x7k2m3n4p5q6r7s8t9v0wx', '01j9x7k3m3n4p5q6r7s8t9v0wx', '01j9x7k4m3n4p5q6r7s8t9v0wx']],
    'string' => [RunStringThings::class, 'string_things', StringThingModel::class, 'string', ['sku-alpha-0001', 'sku-alpine-0001', 'sku-beta-0001']],
]);

it('refuses an empty selection, a sole action on 2 rows, an unknown and a hidden action', function () {
    RunDeclared::$actions = [
        runShipping(),
        Action::make()->withName('Print Label')->withUriKey('print-label')->sole(),
        Action::make()->withName('Secret')->withUriKey('secret')->canSee(fn () => false),
    ];

    $this->postJson('/lathe/api/resources/run-orders/action', runBody('mark-shipped', []))
        ->assertStatus(422)->assertJsonPath('errors.selection', ['Select at least one row.']);
    $this->postJson('/lathe/api/resources/run-orders/action', runBody('print-label', [1, 2]))
        ->assertStatus(422)->assertJsonPath('errors.selection', ['This action requires exactly one selected resource.']);
    $this->postJson('/lathe/api/resources/run-orders/action', runBody('nope', [1]))->assertNotFound();
    $this->postJson('/lathe/api/resources/run-orders/action', runBody('secret', [1]))->assertForbidden();

    expect(RunDeclared::$calls)->toBe([]);
});

it('refuses a user the policy denies runAction or runDestructiveAction', function () {
    $cancel = Action::make()->withName('Cancel Order')->withUriKey('cancel-order')->handleUsing(fn () => RunDeclared::$calls[] = 'cancel');
    $cancel->destructive = true;
    RunDeclared::$actions = [runShipping(), $cancel];

    $this->postJson('/lathe/api/resources/run-guarded-orders/action', runBody('mark-shipped', [1]))->assertOk();
    $this->postJson('/lathe/api/resources/run-guarded-orders/action', runBody('cancel-order', [1]))->assertForbidden();

    $this->actingAs(User::where('email', 'editor@lathephp.com')->firstOrFail());
    $this->postJson('/lathe/api/resources/run-guarded-orders/action', runBody('mark-shipped', [2]))->assertForbidden();

    expect(RunDeclared::$calls)->toBe(['handle:1'])
        ->and(Order::whereKey([1, 2])->orderBy('id')->pluck('status')->all())->toBe(['shipped', 'pending']);
});

it('picks the handler and the failed handler for the model class', function (string $action, array $calls) {
    RunDeclared::$actions = [$action::make()];

    $this->postJson('/lathe/api/resources/run-orders/action', runBody($action::make()->uriKey(), [4]))
        ->assertOk()
        ->assertJsonPath('failed', 1);

    expect(RunDeclared::$calls)->toBe($calls);
})->with([
    'plural' => [RunHandlesOrders::class, ['handleForOrders', 'failedForOrders:boom:4']],
    'singular' => [RunHandlesOrder::class, ['handleForOrder', 'failedForOrder:boom:4']],
    'generic' => [RunHandles::class, ['handle', 'failed:boom:4']],
]);
