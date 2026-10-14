<?php

namespace Lathe\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lathe\ActionEvents\ActionEventRecorder;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Enums\ActionEventStatus;
use Lathe\Events\ActionExecuted;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\Selection;
use Lathe\Query\SelectionResolver;
use Lathe\Resources\Resource;
use LogicException;
use Throwable;

/**
 * Runs an action on the rows of a selection: resolves and authorizes them, validates the
 * modal fields, runs the handler once per row in its own transaction and writes the audit
 * rows. One row that throws never stops the rest.
 */
class ActionDispatcher
{
    /** The most failures a response lists. */
    public const MAX_ERRORS = 50;

    public function __construct(
        protected ActionEventRecorder $recorder,
        protected ResourceAuthorization $authorization,
        protected ActionFieldValidator $validator,
    ) {
        //
    }

    /**
     * @return array{result: ActionResult, permitted: int, skipped: int, failed: int, errors: list<array{id: int|string|null, message: string}>}
     */
    public function dispatch(LatheRequest $request, Action $action, Selection $selection): array
    {
        $class = $request->resourceOrFail();
        $resource = $class::make();

        if ($action->isStandalone()) {
            return $this->runStandalone($request, $action, $class);
        }

        $resolver = new SelectionResolver($resource, $request, $selection);
        $resolver->ensureNotEmpty();

        if ($action->isSole() && $resolver->exceeds(1)) {
            throw ValidationException::withMessages(['selection' => Lathe::__('This action requires exactly one selected resource.')]);
        }

        $max = $resource->definition()->getBulkInlineMax();

        // A queued job cannot carry an uploaded file, so a file field caps the run at the inline size.
        if ($action->hasFileField($request) && $resolver->exceeds($max)) {
            throw ValidationException::withMessages(['selection' => Lathe::__('This action accepts at most :max rows.', ['max' => number_format($max)])]);
        }

        if ($this->shouldQueue($action, $resolver, $max)) {
            throw new LogicException('Queued action runs are not available yet.');
        }

        $fields = $this->validator->validate($request, $action);
        $total = 0;
        $allowed = [];

        foreach ($resolver->lazy(200)->chunk(200) as $chunk) {
            $total += $chunk->count();
            $allowed = [...$allowed, ...$this->filterForExecution($request, $action, $class, $chunk->values()->collect())->all()];
        }

        $permitted = new Collection($allowed);

        // No row of the selection may run, so nothing runs.
        abort_if($permitted->isEmpty(), 403, $this->authorization->denialMessage($class));

        $action->withActionBatchId($this->record($request, $action, $permitted, $class, $fields));
        $run = $this->runChunk($action, $fields, $permitted);
        $failed = count($run['errors']);
        $succeeded = $permitted->count() - $failed;

        event(new ActionExecuted($action, $run['succeeded']));

        if ($failed === 0) {
            $result = $action->handleResult($fields, $run['results']);
            $action->runThen($permitted);
        } elseif ($succeeded > 0) {
            $result = ActionResult::message(Lathe::__(':succeeded succeeded, :failed failed.', ['succeeded' => number_format($succeeded), 'failed' => number_format($failed)]));
        } else {
            $result = ActionResult::danger(Lathe::__('Action failed for all selected resources.'));
        }

        return [
            'result' => $result,
            'permitted' => $permitted->count(),
            'skipped' => $total - $permitted->count(),
            'failed' => $failed,
            'errors' => array_slice($run['errors'], 0, self::MAX_ERRORS),
        ];
    }

    /**
     * The rows of one chunk the action may run on. canRunWhen or canRun decide when the
     * action sets one; otherwise the Resource ability runAction, or runDestructiveAction for
     * a destructive action, asked through the Gate with the action as its argument.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  SupportCollection<int, Model>  $models
     * @return SupportCollection<int, Model>
     */
    public function filterForExecution(LatheRequest $request, Action $action, string $class, SupportCollection $models): SupportCollection
    {
        $ability = $action->isDestructive() ? 'runDestructiveAction' : 'runAction';
        $user = $request->user();

        return $models->filter(fn (Model $model) => $action->authorizedToRunOn($request, $model)
            ?? $this->authorization->allows($user, $class, $ability, $model, [$action]))->values();
    }

    /**
     * Runs the handler for each model's class once per model, each in its own transaction.
     * A throw rolls that row back, calls the failed handler and marks its event failed; the
     * next row runs regardless.
     *
     * @param  Collection<int, Model>  $models
     * @return array{results: list<ActionResult>, succeeded: Collection<int, Model>, errors: list<array{id: int|string|null, message: string}>}
     */
    public function runChunk(Action $action, ActionFields $fields, Collection $models): array
    {
        $results = [];
        $succeeded = [];
        $errors = [];

        foreach ($models as $model) {
            $handler = ActionMethodResolver::handlerFor($action, $model::class);

            try {
                $result = $model->getConnection()->transaction(fn () => $action->{$handler}($fields, new Collection([$model])));
            } catch (Throwable $e) {
                report($e);

                $failedHandler = ActionMethodResolver::failedHandlerFor($action, $model::class);

                if ($failedHandler !== null) {
                    $action->{$failedHandler}($e, $model);
                }

                $action->markAsFailed($model, $e);
                $key = $model->getKey();
                $errors[] = ['id' => is_int($key) || is_string($key) ? $key : null, 'message' => $e->getMessage()];

                continue;
            }

            $action->markAsFinished($model);
            $succeeded[] = $model;

            if ($result instanceof ActionResult) {
                $results[] = $result;
            }
        }

        return ['results' => $results, 'succeeded' => new Collection($succeeded), 'errors' => $errors];
    }

    /**
     * Whether the run goes to the queue instead of running inline. Every run is inline for now.
     */
    protected function shouldQueue(Action $action, SelectionResolver $resolver, int $max): bool
    {
        return false;
    }

    /**
     * One run with no rows, under one audit row without a record. A throw marks that row
     * failed and reaches the caller.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{result: ActionResult, permitted: int, skipped: int, failed: int, errors: list<array{id: int|string|null, message: string}>}
     */
    protected function runStandalone(LatheRequest $request, Action $action, string $class): array
    {
        $fields = $this->validator->validate($request, $action);
        $models = new Collection;
        $batchId = $this->record($request, $action, $models, $class, $fields);
        $modelClass = $class::make()->definition()->getModel();
        $handler = $modelClass === null ? 'handle' : ActionMethodResolver::handlerFor($action, $modelClass);

        try {
            $result = DB::transaction(fn () => $action->{$handler}($fields, $models));
        } catch (Throwable $e) {
            if ($batchId !== null) {
                Lathe::actionEventModel()::markBatchAsFailed($batchId, $e);
            }

            throw $e;
        }

        if ($batchId !== null) {
            Lathe::actionEventModel()::markBatchAsFinished($batchId);
        }

        event(new ActionExecuted($action, $models));
        $action->runThen($models);

        return [
            'result' => $action->handleResult($fields, $result instanceof ActionResult ? [$result] : []),
            'permitted' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];
    }

    /**
     * The `running` audit rows of the run, or null when the action records none.
     *
     * @param  Collection<int, Model>  $models
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    protected function record(LatheRequest $request, Action $action, Collection $models, string $class, ActionFields $fields): ?string
    {
        if (! $action->recordsActionEvents()) {
            return null;
        }

        return $this->recorder->forActionRan($request, $action, $models, $class, ActionEventStatus::Running, $fields->toArray());
    }
}
