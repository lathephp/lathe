<?php

namespace Lathe\Tests\Unit\Actions;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Attributes\Action\Confirmable;
use Lathe\Attributes\Action\Destructive;
use Lathe\Attributes\Action\ModalSize;
use Lathe\Attributes\Action\ModalStyle;
use Lathe\Attributes\Action\Pinned;
use Lathe\Attributes\Action\ShowInline;
use Lathe\Attributes\Action\ShowOnDetail;
use Lathe\Attributes\Action\ShowOnIndex;
use Lathe\Attributes\Action\Sole;
use Lathe\Attributes\Action\Standalone;
use Lathe\Attributes\Action\WithoutActionEvents;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Workbench\App\Models\Order;

class MarkShipped extends Action
{
    //
}

class MergeIntoTarget extends Action
{
    public function fields(LatheRequest $request): array
    {
        return [Field::text('Target')];
    }
}

#[Confirmable('Ship :count orders?')]
class ConfirmableShip extends Action
{
    //
}

#[Destructive]
class DestructiveShip extends Action
{
    //
}

#[ModalSize('lg')]
class LargeShip extends Action
{
    //
}

#[ModalStyle('fullscreen')]
class FullscreenShip extends Action
{
    //
}

#[Pinned]
class PinnedShip extends Action
{
    //
}

#[ShowInline]
class InlineShip extends Action
{
    //
}

#[ShowOnDetail(false)]
class IndexOnlyShip extends Action
{
    //
}

#[ShowOnIndex(false)]
class DetailOnlyShip extends Action
{
    //
}

#[Sole]
class SoleShip extends Action
{
    //
}

#[Standalone]
class StandaloneShip extends Action
{
    //
}

#[WithoutActionEvents]
class SilentShip extends Action
{
    //
}

/**
 * What an action shows the client and records, without the class-derived name and key.
 *
 * @return array<string, mixed>
 */
function actionState(Action $action): array
{
    return [...Arr::except($action->jsonSerialize(), ['name', 'uriKey']), 'recordsActionEvents' => $action->recordsActionEvents()];
}

it('serializes an action with no modifiers to the default metadata', function () {
    $action = MarkShipped::make();

    expect(json_encode($action))->toBe(json_encode([
        'uriKey' => 'mark-shipped',
        'name' => 'Mark Shipped',
        'icon' => null,
        'description' => null,
        'group' => null,
        'destructive' => false,
        'standalone' => false,
        'sole' => false,
        'pinned' => false,
        'withoutConfirmation' => true,
        'confirmText' => null,
        'confirmButtonText' => 'Run Action',
        'cancelButtonText' => 'Cancel',
        'size' => '2xl',
        'style' => 'window',
        'asBlob' => false,
        'showOnIndex' => true,
        'showOnDetail' => true,
        'showOnTableRow' => false,
    ]))
        ->and($action->shownOnIndex())->toBeTrue()
        ->and($action->shownOnDetail())->toBeTrue()
        ->and($action->shownOnTableRow())->toBeFalse()
        ->and($action->recordsActionEvents())->toBeTrue();
});

it('opens the modal for an action that declares fields without confirmable', function () {
    expect(MergeIntoTarget::make()->jsonSerialize()['withoutConfirmation'])->toBeFalse();
});

it('gives the same metadata for an attribute and its fluent twin', function (string $class, Closure $fluent, array $changed) {
    /** @var class-string<Action> $class */
    $attributed = $class::make();
    $twin = $fluent(MarkShipped::make());

    expect(actionState($attributed))->toBe(actionState($twin))
        ->and(Arr::only(actionState($attributed), array_keys($changed)))->toBe($changed);
})->with([
    'Confirmable' => [ConfirmableShip::class, fn (Action $a) => $a->confirmable('Ship :count orders?'), ['withoutConfirmation' => false, 'confirmText' => 'Ship :count orders?']],
    'Destructive' => [DestructiveShip::class, function (Action $a) {
        $a->destructive = true;

        return $a;
    }, ['destructive' => true]],
    'ModalSize' => [LargeShip::class, fn (Action $a) => $a->size('lg'), ['size' => 'lg']],
    'ModalStyle' => [FullscreenShip::class, fn (Action $a) => $a->fullscreen(), ['style' => 'fullscreen']],
    'Pinned' => [PinnedShip::class, fn (Action $a) => $a->pinned(), ['pinned' => true]],
    'ShowInline' => [InlineShip::class, fn (Action $a) => $a->showInline(), ['showOnTableRow' => true]],
    'ShowOnDetail' => [IndexOnlyShip::class, fn (Action $a) => $a->exceptOnDetail(), ['showOnDetail' => false]],
    'ShowOnIndex' => [DetailOnlyShip::class, fn (Action $a) => $a->exceptOnIndex(), ['showOnIndex' => false]],
    'Sole' => [SoleShip::class, fn (Action $a) => $a->sole(), ['sole' => true]],
    'Standalone' => [StandaloneShip::class, fn (Action $a) => $a->standalone(), ['standalone' => true]],
    'WithoutActionEvents' => [SilentShip::class, fn (Action $a) => $a->withoutActionEvents(), ['recordsActionEvents' => false]],
]);

it('lets a fluent call win over the class attribute', function () {
    expect(LargeShip::make()->size('sm')->jsonSerialize()['size'])->toBe('sm')
        ->and(ConfirmableShip::make()->confirmable('Really?')->jsonSerialize()['confirmText'])->toBe('Really?')
        ->and(ConfirmableShip::make()->withoutConfirmation()->isConfirmable())->toBeFalse()
        ->and(InlineShip::make()->exceptInline()->shownOnTableRow())->toBeFalse();
});

it('makes standalone factory actions with no confirmation and a kebab uriKey', function (Action $action, string $uriKey, string $name) {
    expect($action->isStandalone())->toBeTrue()
        ->and($action->isConfirmable())->toBeFalse()
        ->and($action->uriKey())->toBe($uriKey)
        ->and($action->name())->toBe($name);
})->with([
    'using' => fn () => [Action::using('Recalculate Totals', fn () => null), 'recalculate-totals', 'Recalculate Totals'],
    'redirect' => fn () => [Action::redirect('Open Reports', '/reports'), 'open-reports', 'Open Reports'],
    'visit' => fn () => [Action::visit('Visit Orders', '/lathe/resources/orders'), 'visit-orders', 'Visit Orders'],
    'openInNewTab' => fn () => [Action::openInNewTab('Open Docs', 'https://example.com/docs'), 'open-docs', 'Open Docs'],
    'downloadURL' => fn () => [Action::downloadURL('Get Manual', '/manual.pdf'), 'get-manual', 'Get Manual'],
    'modal' => fn () => [Action::modal('Hold Order', 'hold-order', ['reason' => 'stock']), 'hold-order', 'Hold Order'],
    'danger' => fn () => [Action::danger('Warn Staff', 'Stock is low.'), 'warn-staff', 'Warn Staff'],
]);

it('runs the factory handlers to their results', function () {
    $fields = new ActionFields;
    $none = new Collection;

    expect(json_encode(Action::redirect('Open Reports', fn () => '/reports')->handle($fields, $none)))
        ->toBe('{"message":null,"level":"success","effect":{"type":"redirect","url":"\/reports","newTab":false}}')
        ->and(json_encode(Action::openInNewTab('Open Docs', 'https://example.com/docs')->handle($fields, $none)))
        ->toBe('{"message":null,"level":"success","effect":{"type":"redirect","url":"https:\/\/example.com\/docs","newTab":true}}')
        ->and(json_encode(Action::visit('Visit Orders', '/orders', ['replace' => true])->handle($fields, $none)))
        ->toBe('{"message":null,"level":"success","effect":{"type":"visit","path":"\/orders","options":{"replace":true}}}')
        ->and(json_encode(Action::downloadURL('Get Manual', '/manual.pdf')->handle($fields, $none)))
        ->toBe('{"message":null,"level":"success","effect":{"type":"download","name":"Get Manual","url":"\/manual.pdf"}}')
        ->and(json_encode(Action::modal('Hold Order', 'hold-order', fn () => ['reason' => 'stock'])->handle($fields, $none)))
        ->toBe('{"message":null,"level":"success","effect":{"type":"modal","component":"hold-order","data":{"reason":"stock"}}}')
        ->and(json_encode(Action::danger('Warn Staff', 'Stock is low.')->handle($fields, $none)))
        ->toBe('{"message":"Stock is low.","level":"danger","effect":null}')
        ->and(Action::using('Recalculate', fn () => ActionResult::message('Recalculated.'))->handle($fields, $none)->jsonSerialize()['message'])
        ->toBe('Recalculated.');
});

it('answers the default message from handle and from __invoke', function () {
    $action = MarkShipped::make();

    expect($action->handle(new ActionFields, new Collection)->jsonSerialize()['message'])->toBe('Action was executed successfully.')
        ->and($action(new ActionFields, new Collection)->jsonSerialize()['message'])->toBe('Action was executed successfully.');
});

it('answers "No operation performed." from a noop action', function () {
    expect(MarkShipped::make()->noop()->handle(new ActionFields, new Collection)->jsonSerialize()['message'])
        ->toBe('No operation performed.');
});

it('stores the then callback and runs it with the models', function () {
    $seen = null;
    $action = MarkShipped::make()->then(function (Collection $models) use (&$seen) {
        $seen = $models->pluck('id')->all();
    });

    $action->runThen(new Collection([(new Order)->forceFill(['id' => 4]), (new Order)->forceFill(['id' => 9])]));

    expect($seen)->toBe([4, 9]);
});

it('takes the last result as the default aggregate', function () {
    $results = [ActionResult::message('First.'), ActionResult::danger('Last.')];

    expect(MarkShipped::make()->handleResult(new ActionFields, $results)->jsonSerialize())
        ->toBe(['message' => 'Last.', 'level' => 'danger', 'effect' => null]);
});

it('reads its run callbacks', function () {
    $request = LatheRequest::createFrom(Request::create('/lathe'));
    $pending = new Order(['status' => 'pending']);
    $shipped = new Order(['status' => 'shipped']);
    $whenPending = MarkShipped::make()->canRunWhen(fn (Order $order) => $order->status === 'pending');

    expect(MarkShipped::make()->authorizedToRunOn($request, $pending))->toBeNull()
        ->and(MarkShipped::make()->hasRunCallback())->toBeFalse()
        ->and($whenPending->hasRunCallback())->toBeTrue()
        ->and($whenPending->authorizedToRunOn($request, $pending))->toBeTrue()
        ->and($whenPending->authorizedToRunOn($request, $shipped))->toBeFalse()
        ->and(MarkShipped::make()->canRun(fn ($request, Order $order) => false)->authorizedToRunOn($request, $pending))->toBeFalse()
        ->and(MarkShipped::make()->canSee(fn () => false)->authorizedToSee($request))->toBeFalse()
        ->and(MarkShipped::make()->authorizedToSee($request))->toBeTrue();
});

it('fills the confirm text for a count, a closure called with it', function () {
    expect(Action::make()->confirmable('Ship :count orders?')->confirmTextFor(3))->toBe('Ship 3 orders?')
        ->and(Action::make()->confirmable(fn (int $count) => $count === 1 ? 'Ship this order?' : "Ship {$count} orders?")->confirmTextFor(1))->toBe('Ship this order?')
        ->and(Action::make()->confirmTextFor(2))->toBeNull();
});
