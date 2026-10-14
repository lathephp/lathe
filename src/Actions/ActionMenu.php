<?php

namespace Lathe\Actions;

use Illuminate\Database\Eloquent\Model;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;

/**
 * The actions a request may see on a Resource, as the Actions menu lists them, and which of
 * them the user may run on a row. Neither reads the database or resolves an action's fields.
 */
class ActionMenu
{
    public function __construct(
        protected LatheRequest $request,
        protected ResourceAuthorization $authorization,
    ) {
        //
    }

    /**
     * The menu for one display. With a model, each action says whether the user may run it there.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{actions: list<array<string, mixed>>, pivotActions: list<array{name: string, actions: list<array<string, mixed>>}>, counts: array{sole: int, standalone: int, resource: int}}
     */
    public function build(string $class, string $display, ?Model $model = null): array
    {
        $actions = $this->visible($class)->forDisplay($display)->menuOrder();

        return [
            'actions' => array_map(fn (Action $action) => [
                ...$action->jsonSerialize(),
                ...($model !== null ? ['authorizedToRun' => $this->authorizedToRun($class, $action, $model)] : []),
            ], array_values($actions->all())),
            'pivotActions' => [],
            'counts' => $actions->counts(),
        ];
    }

    /**
     * The actions a row may offer: every visible one that runs on rows, in menu order.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    public function forRows(string $class): ActionCollection
    {
        return $this->visible($class)->reject(fn (Action $action) => $action->isStandalone())->menuOrder();
    }

    /**
     * The uriKeys of the actions the user may run on one row.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return list<string>
     */
    public function runnableOn(string $class, ActionCollection $actions, Model $model): array
    {
        return array_map(
            fn (Action $action) => $action->uriKey(),
            array_values($actions->filter(fn (Action $action) => $this->authorizedToRun($class, $action, $model))->all()),
        );
    }

    /**
     * A run callback decides alone; otherwise the runAction ability, and for a destructive
     * action runDestructiveAction as well. A standalone action needs no row, so it always runs.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    public function authorizedToRun(string $class, Action $action, Model $model): bool
    {
        if ($action->isStandalone()) {
            return true;
        }

        $allowed = $action->authorizedToRunOn($this->request, $model);

        if ($allowed !== null) {
            return $allowed;
        }

        $user = $this->request->user();

        return $this->authorization->allows($user, $class, 'runAction', $model, [$action])
            && (! $action->isDestructive() || $this->authorization->allows($user, $class, 'runDestructiveAction', $model, [$action]));
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    protected function visible(string $class): ActionCollection
    {
        return $class::make()->availableActions($this->request)->authorizedToSee($this->request);
    }
}
