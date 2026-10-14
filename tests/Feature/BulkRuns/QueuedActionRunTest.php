<?php

namespace Lathe\Tests\Feature\BulkRuns;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\BatchFake;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Actions\BatchableAction;
use Lathe\Actions\BulkRun;
use Lathe\Actions\Jobs\ExecuteAction;
use Lathe\Actions\Jobs\PlanBulkRun;
use Lathe\Events\ActionExecuted;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use RuntimeException;
use Throwable;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class QueuedCalls
{
    /** @var list<Action> */
    public static array $actions = [];

    /** @var list<string> */
    public static array $log = [];

    /** @var list<int> keys whose chunk throws */
    public static array $throwOn = [];

    public static bool $throwAll = false;

    /** @var list<array<string, mixed>> */
    public static array $context = [];
}

class QueuedOrders extends Resource
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
        return QueuedCalls::$actions;
    }
}

class SmallQueuedOrders extends QueuedOrders
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->bulkInlineMax(100);
    }
}

/** Ships each row; a chunk holding a key of $throwOn throws after its writes. */
class QueuedShip extends Action
{
    public function name(): string
    {
        return 'Mark Shipped';
    }

    public function uriKey(): string
    {
        return 'mark-shipped';
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        QueuedCalls::$log[] = 'handle:'.$models->count().':'.DB::transactionLevel();
        QueuedCalls::$context[] = ['user' => Auth::id(), 'locale' => app()->getLocale()];

        foreach ($models as $order) {
            $order->update(['status' => 'shipped']);
        }

        if (QueuedCalls::$throwAll || $models->contains(fn (Model $model) => in_array($model->getKey(), QueuedCalls::$throwOn, true))) {
            throw new RuntimeException('Chunk on hold.');
        }

        return ActionResult::message('Shipped.');
    }

    public function failed(Throwable $e, Model $model): void
    {
        QueuedCalls::$log[] = "failed:{$model->getKey()}";
    }
}

class QueuedBigChunks extends QueuedShip
{
    public static int $chunkCount = 400;

    public ?string $connection = 'database';

    public ?string $queue = 'bulk';

    public ?int $timeout = 120;

    public ?int $tries = 3;
}

class QueuedAlways extends QueuedShip implements ShouldQueue
{
    //
}

class QueuedBatchable extends QueuedShip implements BatchableAction
{
    public function withBatch(ActionFields $fields, PendingBatch $batch): void
    {
        $batch->then(static function (): void {
            QueuedCalls::$log[] = 'then';
        })->catch(static function (): void {
            QueuedCalls::$log[] = 'catch';
        })->finally(static function (): void {
            QueuedCalls::$log[] = 'finally';
        });
    }
}

/**
 * @param  array<string, mixed>  $selection
 * @return array<string, mixed>
 */
function queuedBody(array $selection, string $action = 'mark-shipped'): array
{
    return ['action' => $action, 'selection' => $selection, 'fields' => []];
}

function seedOrders(int $count): void
{
    (new DemoSeeder)->forOrders($count)->run();
    Order::where('status', '!=', 'pending')->update(['status' => 'pending']);
}

/**
 * Runs the queued jobs on the database queue: all of them, or the next $jobs.
 */
function drainQueue(?int $jobs = null): void
{
    if ($jobs === null) {
        test()->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();

        return;
    }

    foreach (range(1, $jobs) as $ignored) {
        test()->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->assertSuccessful();
    }
}

/**
 * The planner of the one faked batch, run against a fake batch.
 *
 * @return array{0: PlanBulkRun, 1: BatchFake}
 */
function runFakedPlanner(): array
{
    $planner = null;
    Bus::assertBatched(function (PendingBatch $batch) use (&$planner) {
        $planner = $batch->jobs->first();

        return true;
    });

    [$job, $batch] = $planner->withFakeBatch();
    app()->call([$job, 'handle']);

    return [$job, $batch];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    QueuedCalls::$actions = [new QueuedShip];
    QueuedCalls::$log = [];
    QueuedCalls::$throwOn = [];
    QueuedCalls::$throwAll = false;
    QueuedCalls::$context = [];
    Lathe::registerResources(QueuedOrders::class, SmallQueuedOrders::class);
});

function signInAdmin(): User
{
    $admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    test()->actingAs($admin);

    return $admin;
}

it('runs exactly 500 rows inline and queues 501 behind one planner job', function () {
    seedOrders(1001);
    signInAdmin();
    Bus::fake();

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'ids', 'ids' => range(1, 500)]))
        ->assertOk()
        ->assertJsonMissingPath('bulk_run')
        ->assertJsonPath('permitted', 500);

    Bus::assertNothingBatched();

    $response = $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'ids', 'ids' => range(1, 501)]))
        ->assertOk();

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 1 && $batch->jobs->first() instanceof PlanBulkRun);

    $run = BulkRun::sole();
    expect($response->json())->toBe([
        'result' => ['message' => 'This action is being processed...', 'level' => 'success', 'effect' => null],
        'permitted' => 501,
        'skipped' => 0,
        'failed' => 0,
        'errors' => [],
        'bulk_run' => ['id' => $run->id, 'total' => 501],
    ]);
});

it('queues 101 rows on a Resource with bulkInlineMax(100) and keeps 100 inline', function () {
    seedOrders(200);
    signInAdmin();
    Bus::fake();

    $this->postJson('/lathe/api/resources/small-queued-orders/action', queuedBody(['mode' => 'ids', 'ids' => range(1, 100)]))
        ->assertOk()->assertJsonMissingPath('bulk_run');
    Bus::assertNothingBatched();

    $this->postJson('/lathe/api/resources/small-queued-orders/action', queuedBody(['mode' => 'ids', 'ids' => range(1, 101)]))
        ->assertOk()->assertJsonPath('bulk_run.total', 101);
    Bus::assertBatchCount(1);
});

it('queues a ShouldQueue action for a single row', function () {
    seedOrders(10);
    signInAdmin();
    QueuedCalls::$actions = [new QueuedAlways];
    Bus::fake();

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'ids', 'ids' => [1]]))
        ->assertOk()->assertJsonPath('bulk_run.total', 1);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->first() instanceof PlanBulkRun);
    expect(QueuedCalls::$log)->toBe([]);
});

it('writes a waiting bulk run under the job batch id', function () {
    seedOrders(1001);
    $admin = signInAdmin();
    config(['queue.default' => 'database']);

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->assertOk();

    $run = BulkRun::sole();
    expect(Str::isUlid($run->id))->toBeTrue()
        ->and($run->status)->toBe('waiting')
        ->and((string) $run->user_id)->toBe((string) $admin->id)
        ->and($run->resource_class)->toBe(QueuedOrders::class)
        ->and($run->action_name)->toBe('Mark Shipped')
        ->and($run->total)->toBe(1001)
        ->and($run->batch_id)->toBe(DB::table('job_batches')->value('id'))
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(QueuedCalls::$log)->toBe([]);
});

it('plans one chunk job per 200 rows with waiting events under the batch id', function () {
    seedOrders(1001);
    signInAdmin();
    Bus::fake();

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->assertOk();
    [, $batch] = runFakedPlanner();

    expect($batch->added)->toHaveCount(6)
        ->and(collect($batch->added)->every(fn (mixed $job) => $job instanceof ExecuteAction))->toBeTrue()
        ->and(ActionEvent::where('batch_id', $batch->id)->where('status', 'waiting')->count())->toBe(1001)
        ->and(BulkRun::sole()->status)->toBe('running');
});

it('plans by the action chunk count and hands the jobs its connection, queue, timeout and tries', function () {
    seedOrders(1001);
    signInAdmin();
    QueuedCalls::$actions = [new QueuedBigChunks];
    Bus::fake();

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->assertOk();
    [, $batch] = runFakedPlanner();

    expect($batch->added)->toHaveCount(3)
        ->and(collect($batch->added)->map(fn (ExecuteAction $job) => [$job->connection, $job->queue, $job->timeout, $job->tries])->unique()->all())
        ->toBe([['database', 'bulk', 120, 3]]);
});

it('runs a chunk with one handle call in one transaction and marks its events finished', function () {
    seedOrders(1001);
    signInAdmin();
    Bus::fake();

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->assertOk();
    [, $planned] = runFakedPlanner();
    [$chunk] = $planned->added[0]->withFakeBatch($planned->id);
    app()->call([$chunk, 'handle']);

    expect(QueuedCalls::$log)->toBe(['handle:200:1'])
        ->and(ActionEvent::where('status', 'finished')->pluck('actionable_id')->map(fn ($id) => (int) $id)->sort()->values()->all())->toBe(range(1, 200))
        ->and(ActionEvent::where('status', 'waiting')->count())->toBe(801)
        ->and(Order::where('status', 'shipped')->whereKey(range(1, 200))->count())->toBe(200);
});

it('fails the whole chunk when handle throws, calls failed per model and rethrows', function () {
    seedOrders(1001);
    signInAdmin();
    QueuedCalls::$throwAll = true;
    Bus::fake();

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->assertOk();
    [, $planned] = runFakedPlanner();
    [$chunk] = $planned->added[0]->withFakeBatch($planned->id);

    expect(fn () => app()->call([$chunk, 'handle']))->toThrow(RuntimeException::class, 'Chunk on hold.');

    expect(array_slice(QueuedCalls::$log, 0, 3))->toBe(['handle:200:1', 'failed:1', 'failed:2'])
        ->and(QueuedCalls::$log)->toHaveCount(201)
        ->and(ActionEvent::where('status', 'failed')->where('exception', 'like', '%Chunk on hold.%')->count())->toBe(200)
        ->and(Order::where('status', 'shipped')->whereKey(range(1, 200))->count())->toBe(0);
});

it('drains a 1,001-row run to finished', function () {
    seedOrders(1001);
    signInAdmin();
    config(['queue.default' => 'database']);

    $id = $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->json('bulk_run.id');
    drainQueue();

    $this->getJson("/lathe/api/bulk-runs/{$id}")
        ->assertOk()
        ->assertExactJson(['status' => 'finished', 'total' => 1001, 'processed' => 1001, 'failed' => 0, 'skipped' => 0]);
    expect(Order::where('status', 'shipped')->count())->toBe(1001);
});

it('counts the failed chunk of a run and sends no notification', function () {
    seedOrders(1001);
    signInAdmin();
    Notification::fake();
    config(['queue.default' => 'database']);
    QueuedCalls::$throwOn = [401];

    $id = $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->json('bulk_run.id');
    drainQueue();

    $this->getJson("/lathe/api/bulk-runs/{$id}")
        ->assertExactJson(['status' => 'finished', 'total' => 1001, 'processed' => 1001, 'failed' => 200, 'skipped' => 0]);
    expect(ActionEvent::where('status', 'finished')->count())->toBe(801)
        ->and(ActionEvent::where('status', 'failed')->count())->toBe(200);
    Notification::assertNothingSent();
});

it('ends a run whose every chunk throws as failed and sends no notification', function () {
    seedOrders(1001);
    signInAdmin();
    Notification::fake();
    config(['queue.default' => 'database']);
    QueuedCalls::$throwAll = true;

    $id = $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->json('bulk_run.id');
    drainQueue();

    $this->getJson("/lathe/api/bulk-runs/{$id}")
        ->assertExactJson(['status' => 'failed', 'total' => 1001, 'processed' => 1001, 'failed' => 1001, 'skipped' => 0]);
    Notification::assertNothingSent();
});

it('skips the later chunks once the run is cancelled', function () {
    seedOrders(1000);
    signInAdmin();
    config(['queue.default' => 'database']);

    $id = $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->json('bulk_run.id');
    drainQueue(3);
    $this->deleteJson("/lathe/api/bulk-runs/{$id}")->assertOk();
    drainQueue();

    expect(QueuedCalls::$log)->toBe(['handle:200:1', 'handle:200:1'])
        ->and(ActionEvent::where('status', 'finished')->count())->toBe(400)
        ->and(ActionEvent::where('status', 'failed')->where('exception', 'Cancelled')->count())->toBe(600)
        ->and(BulkRun::findOrFail($id)->status)->toBe('cancelled');
});

it('runs the chunks as the starting user in the captured locale and context', function () {
    seedOrders(501);
    $admin = signInAdmin();
    config(['queue.default' => 'database']);
    $restored = [];
    Lathe::captureJobContext(fn () => ['tenant' => 'acme']);
    Lathe::restoreJobContext(function (array $extra) use (&$restored) {
        $restored[] = $extra['tenant'] ?? null;
    });
    app()->setLocale('fr');

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->assertOk();

    app()->setLocale('en');
    Auth::forgetGuards();
    drainQueue();

    expect(QueuedCalls::$context)->toHaveCount(3)
        ->and(collect(QueuedCalls::$context)->unique()->values()->all())->toBe([['user' => $admin->id, 'locale' => 'fr']])
        ->and(array_unique($restored))->toBe(['acme']);
});

it('runs the then callback of a BatchableAction once, after the last chunk', function () {
    seedOrders(1001);
    signInAdmin();
    config(['queue.default' => 'database']);
    QueuedCalls::$actions = [new QueuedBatchable];

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->assertOk();
    drainQueue();

    expect(QueuedCalls::$log)->toBe([
        'handle:200:1', 'handle:200:1', 'handle:200:1', 'handle:200:1', 'handle:200:1', 'handle:1:1', 'then', 'finally',
    ]);
});

it('fires ActionExecuted once per chunk of a queued run', function () {
    seedOrders(1001);
    signInAdmin();
    config(['queue.default' => 'database']);
    Event::fake([ActionExecuted::class]);

    $this->postJson('/lathe/api/resources/queued-orders/action', queuedBody(['mode' => 'all']))->assertOk();
    drainQueue();

    Event::assertDispatchedTimes(ActionExecuted::class, 6);
});
