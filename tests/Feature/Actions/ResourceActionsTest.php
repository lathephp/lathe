<?php

namespace Lathe\Tests\Feature\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Lathe\Actions\Action;
use Lathe\Exceptions\ActionUriKeyCollisionException;
use Lathe\Exceptions\InvalidActionException;
use Lathe\Field\Field;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;

class ShipOrders extends Action
{
    //
}

class QueuedImportLabels extends Action implements ShouldQueue
{
    public function fields(LatheRequest $request): array
    {
        return [FileField::make('Labels')];
    }
}

class QueuedShipOrders extends Action implements ShouldQueue
{
    public function fields(LatheRequest $request): array
    {
        return [Field::make('Note')];
    }
}

class ActionOrders extends Resource
{
    /** @var list<Action> */
    public static array $declared = [];

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public function actions(LatheRequest $request): array
    {
        return static::$declared;
    }
}

function actionsRequest(): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/action-orders'));
}

it('offers no actions by default', function () {
    $resource = new class extends Resource
    {
        public function fields(LatheRequest $request): array
        {
            return [];
        }
    };

    expect($resource->actions(actionsRequest()))->toBe([])
        ->and($resource->availableActions(actionsRequest())->all())->toBe([]);
});

it('returns the declared actions, hidden ones included', function () {
    ActionOrders::$declared = [ShipOrders::make(), Action::make()->withUriKey('secret')->canSee(fn () => false)];

    expect(ActionOrders::make()->availableActions(actionsRequest())->map(fn (Action $action) => $action->uriKey())->all())
        ->toBe(['ship-orders', 'secret']);
});

it('throws when two actions share a uriKey', function () {
    ActionOrders::$declared = [ShipOrders::make(), Action::make()->withName('Ship Again')->withUriKey('ship-orders')];

    ActionOrders::make()->availableActions(actionsRequest());
})->throws(ActionUriKeyCollisionException::class, 'ship-orders');

it('queued_action_with_file_field_is_rejected_at_definition', function () {
    ActionOrders::$declared = [QueuedImportLabels::make()];

    ActionOrders::make()->availableActions(actionsRequest());
})->throws(InvalidActionException::class, QueuedImportLabels::class)->group('regression');

it('accepts a queued action without a file field and reports file fields', function () {
    ActionOrders::$declared = [QueuedShipOrders::make()];

    expect(ActionOrders::make()->availableActions(actionsRequest())->count())->toBe(1)
        ->and(QueuedImportLabels::make()->hasFileField(actionsRequest()))->toBeTrue()
        ->and(QueuedShipOrders::make()->hasFileField(actionsRequest()))->toBeFalse();
});
