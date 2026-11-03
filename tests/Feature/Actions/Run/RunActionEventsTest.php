<?php

namespace Lathe\Tests\Feature\Actions\Run;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Enums\ActionEventStatus;
use Lathe\Enums\ActionEventType;
use Lathe\Events\ActionExecuted;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use RuntimeException;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class RunAuditOrders extends Resource
{
    /** @var list<int> */
    public static array $models = [];

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
        return [
            Action::make()->withName('Mark Shipped')->withUriKey('mark-shipped')->handleUsing(function (ActionFields $fields, Collection $models) {
                foreach ($models as $order) {
                    if ($order->getKey() === 13) {
                        throw new RuntimeException('Order 13 is on hold.');
                    }

                    $order->update(['status' => 'shipped']);
                }
            }),
            Action::make()->withName('Rebuild Index')->withUriKey('rebuild-index')->standalone()->handleUsing(function (ActionFields $fields, Collection $models) {
                static::$models[] = $models->count();
            }),
            Action::make()->withName('Refund')->withUriKey('refund')->handleUsing(
                fn (ActionFields $fields, Collection $models) => ActionResult::danger('Order '.$models->firstOrFail()->getKey().' is past refund.'),
            )->then(function (Collection $models) {
                static::$models[] = $models->count();
            }),
            Action::make()->withName('Note')->withUriKey('note')->handleUsing(fn () => ActionResult::message('Noted.')),
            Action::make()->withName('Refuse All')->withUriKey('refuse-all')->standalone()->handleUsing(fn () => ActionResult::danger('Nothing to refuse.')),
            new RunAuditSigned,
        ];
    }
}

class RunAuditSigned extends Action
{
    public function fields(LatheRequest $request): array
    {
        return [Field::password('Code', 'code'), Field::text('Note')];
    }
}

/**
 * @param  list<int>  $ids
 * @return array<string, mixed>
 */
function runAuditBody(string $action, array $ids): array
{
    return ['action' => $action, 'selection' => ['mode' => 'ids', 'ids' => $ids], 'fields' => []];
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    RunAuditOrders::$models = [];
    Lathe::registerResources(RunAuditOrders::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('action_records_one_event_set', function () {
    $this->postJson('/lathe/api/resources/run-audit-orders/action', runAuditBody('mark-shipped', [1, 2]))->assertOk();

    $rows = ActionEvent::orderBy('actionable_id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('type')->unique()->all())->toBe([ActionEventType::ActionRan])
        ->and($rows->pluck('actionable_id')->all())->toBe(['1', '2'])
        ->and($rows->pluck('action_name')->unique()->all())->toBe(['Mark Shipped'])
        ->and($rows->pluck('batch_id')->unique())->toHaveCount(1)
        ->and($rows->pluck('status')->unique()->all())->toBe([ActionEventStatus::Finished]);
})->group('regression');

it('marks the row that threw as failed with its message', function () {
    $this->postJson('/lathe/api/resources/run-audit-orders/action', runAuditBody('mark-shipped', [12, 13]))->assertOk();

    $rows = ActionEvent::orderBy('actionable_id')->get();

    expect($rows->pluck('status', 'actionable_id')->all())->toBe(['12' => ActionEventStatus::Finished, '13' => ActionEventStatus::Failed])
        ->and($rows[1]->exception)->toBe('Order 13 is on hold.');
});

it('runs a standalone action once with no rows and records one row without a record', function () {
    $this->postJson('/lathe/api/resources/run-audit-orders/action', ['action' => 'rebuild-index'])
        ->assertOk()
        ->assertJsonPath('result.message', 'Action was executed successfully.');

    $row = ActionEvent::sole();

    expect(RunAuditOrders::$models)->toBe([0])
        ->and($row->actionable_id)->toBeNull()
        ->and($row->status)->toBe(ActionEventStatus::Finished);
});

it('inserts the event rows of 120 models in chunks of 50', function () {
    DB::enableQueryLog();

    $this->postJson('/lathe/api/resources/run-audit-orders/action', runAuditBody('mark-shipped', range(14, 133)))->assertOk();

    $inserts = collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/^insert into .?lathe_action_events/', $query['query']) === 1);

    expect($inserts)->toHaveCount(3)
        ->and(ActionEvent::where('status', ActionEventStatus::Finished->value)->count())->toBe(120);
});

it('stores a password action field as redacted whatever its name', function () {
    $this->postJson('/lathe/api/resources/run-audit-orders/action', [
        'action' => 'run-audit-signed', 'selection' => ['mode' => 'ids', 'ids' => [12]], 'fields' => ['code' => '4711', 'note' => 'ok'],
    ])->assertOk();

    expect(ActionEvent::sole()->fields)->toBe(['code' => '[redacted]', 'note' => 'ok']);
});

it('fires ActionExecuted once for an inline run', function () {
    Event::fake([ActionExecuted::class]);

    $this->postJson('/lathe/api/resources/run-audit-orders/action', runAuditBody('mark-shipped', [1, 2]))->assertOk();

    Event::assertDispatchedTimes(ActionExecuted::class, 1);
    Event::assertDispatched(ActionExecuted::class, fn (ActionExecuted $event) => $event->models->map(fn (Model $model) => $model->getKey())->all() === [1, 2]);
});

it('records a run whose handler returns danger as failed with the message, row by row', function () {
    $this->postJson('/lathe/api/resources/run-audit-orders/action', runAuditBody('refund', [4, 5, 6]))
        ->assertOk()
        ->assertJsonPath('result.level', 'danger')
        ->assertJsonPath('result.message', 'Order 6 is past refund.')
        ->assertJsonPath('failed', 0);

    $rows = ActionEvent::orderBy('actionable_id')->get();

    expect(RunAuditOrders::$models)->toBe([3])
        ->and($rows->pluck('status', 'actionable_id')->all())->toBe(['4' => ActionEventStatus::Failed, '5' => ActionEventStatus::Failed, '6' => ActionEventStatus::Failed])
        ->and($rows->pluck('exception')->all())->toBe(['Order 4 is past refund.', 'Order 5 is past refund.', 'Order 6 is past refund.']);
});

it('records a run whose handler returns a message as finished', function () {
    $this->postJson('/lathe/api/resources/run-audit-orders/action', runAuditBody('note', [4, 5, 6]))->assertOk();

    expect(ActionEvent::pluck('status')->unique()->all())->toBe([ActionEventStatus::Finished])
        ->and(ActionEvent::whereNotNull('exception')->count())->toBe(0);
});

it('records a standalone run whose handler returns danger as failed with the message', function () {
    $this->postJson('/lathe/api/resources/run-audit-orders/action', ['action' => 'refuse-all'])
        ->assertOk()
        ->assertJsonPath('result.level', 'danger');

    $row = ActionEvent::sole();

    expect($row->status)->toBe(ActionEventStatus::Failed)
        ->and($row->exception)->toBe('Nothing to refuse.');
});
