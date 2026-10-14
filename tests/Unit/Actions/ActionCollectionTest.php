<?php

namespace Lathe\Tests\Unit\Actions;

use Illuminate\Http\Request;
use Lathe\Actions\Action;
use Lathe\Actions\ActionCollection;
use Lathe\Actions\DestructiveAction;
use Lathe\Http\Requests\LatheRequest;

class CancelOrder extends DestructiveAction
{
    //
}

/**
 * The Orders menu: a destructive action, a grouped one, a standalone one and an ungrouped one,
 * declared out of menu order.
 */
function ordersMenu(): ActionCollection
{
    return new ActionCollection([
        CancelOrder::make(),
        Action::make()->withName('Mark Shipped')->withUriKey('mark-shipped')->group('Fulfilment'),
        Action::make()->withName('Print Labels')->withUriKey('print-labels')->group('Fulfilment')->sole(),
        Action::make()->withName('Export')->withUriKey('export'),
        Action::make()->withName('Import')->withUriKey('import')->standalone(),
        Action::make()->withName('Archive')->withUriKey('archive')->exceptOnIndex()->showInline(),
    ]);
}

it('orders the menu standalone first, ungrouped next, then groups, destructive last', function () {
    expect(ordersMenu()->menuOrder()->map(fn (Action $action) => $action->uriKey())->all())
        ->toBe(['import', 'export', 'archive', 'mark-shipped', 'print-labels', 'cancel-order']);
});

it('filters by display', function () {
    $keys = fn (ActionCollection $actions) => $actions->map(fn (Action $action) => $action->uriKey())->all();

    expect($keys(ordersMenu()->forDisplay('index')))->toBe(['cancel-order', 'mark-shipped', 'print-labels', 'export', 'import'])
        ->and($keys(ordersMenu()->forDisplay('table-row')))->toBe(['archive'])
        ->and($keys(ordersMenu()->forDisplay('detail')))->toBe(['cancel-order', 'mark-shipped', 'print-labels', 'export', 'import', 'archive']);
});

it('filters out the actions the request may not see', function () {
    $request = LatheRequest::createFrom(Request::create('/lathe'));
    $actions = new ActionCollection([
        Action::make()->withUriKey('seen'),
        Action::make()->withUriKey('hidden')->canSee(fn () => false),
    ]);

    expect($actions->authorizedToSee($request)->map(fn (Action $action) => $action->uriKey())->all())->toBe(['seen']);
});

it('counts the actions by type and finds one by uriKey', function () {
    expect(ordersMenu()->counts())->toBe(['sole' => 1, 'standalone' => 1, 'resource' => 4])
        ->and(ordersMenu()->findByUriKey('export')?->name())->toBe('Export')
        ->and(ordersMenu()->findByUriKey('missing'))->toBeNull();
});

it('marks a DestructiveAction destructive and confirmable', function () {
    $action = CancelOrder::make();

    expect($action->isDestructive())->toBeTrue()
        ->and($action->isConfirmable())->toBeTrue()
        ->and($action->jsonSerialize()['destructive'])->toBeTrue()
        ->and($action->jsonSerialize()['withoutConfirmation'])->toBeFalse();
});
