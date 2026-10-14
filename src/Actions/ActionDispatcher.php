<?php

namespace Lathe\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lathe\ActionEvents\ActionEvent;
use Lathe\ActionEvents\ActionEventRecorder;
use Lathe\Actions\Jobs\PlanBulkRun;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Enums\ActionEventStatus;
use Lathe\Events\ActionExecuted;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\Selection;
use Lathe\Query\SelectionResolver;
use Lathe\Resources\Resource;
use Lathe\Support\JobContext;
use Lathe\Widgets\WidgetCache;
use Throwable;

/**
 * Runs an action on the rows of a selection: resolves and authorizes them, validates the
 * modal fields, runs the handler once per row in its own transaction and writes the audit
 * rows. One row that throws never stops the rest. A ShouldQueue action, or a selection over
 * the Resource's inline limit, goes to the queue as a bulk run instead.
 */
class ActionDispatcher
{
    /** The most failures a response lists. */
    public const int MAX_ERRORS = 50;

    public function __construct(
        protected ActionEventRecorder $recorder,
        protected ResourceAuthorization $authorization,
        protected ActionFieldValidator $validator,
    ) {
        //
    }

    /**
     * @return array{result: ActionResult, permitted: int, skipped: int, failed: int, errors: list<array{id: int|string|null, message: string}>, bulk_run?: array{id: string, total: int}}
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

        $fields = $this->validator->validate($request, $action);

        if ($this->shouldQueue($action, $resolver, $max)) {
            // The worker checks every row; the first chunk answers 403 here when none of it may run.
            $first = $resolver->lazy(200)->take(200)->collect();
            abort_if($this->filterForExecution($request, $action, $class, $first)->isEmpty(), 403, $this->authorization->denialMessage($class));

            return $this->queue($request, $action, $class, $selection, $resolver, $fields);
        }

        $total = 0;
        $allowed = [];

        foreach ($resolver->lazy(200)->chunk(200) as $chunk) {
            $total += $chunk->count();
            $allowed = [...$allowed, ...$this->filterForExecution($request, $action, $class, $chunk->values()->collect())->all()];
        }

        $permitted = new Collection($allowed);
        $via = $resolver->pivotVia();

        // Through a via, each model carries its pivot row, read again through the parent's relation.
        if ($via !== null && $permitted->isNotEmpty()) {
            /** @var BelongsToMany<Model, Model> $relation */
            $relation = $via->parent->{$via->field->attribute()}();
            $pivoted = $relation->whereKey($permitted->modelKeys())->get();
            $permitted = new Collection($permitted->map(function (Model $model) use ($pivoted): Model {
                $found = $pivoted->find($model->getKey());

                return $found instanceof Model ? $found : $model;
            })->all());
        }

        // A requested key the selection does not hold, such as a tag the parent lacks, counts as skipped.
        if (! $selection->isAll()) {
            $total = max($total, count(array_unique($selection->ids)));
        }

        // No row of the selection may run, so nothing runs.
        abort_if($permitted->isEmpty(), 403, $this->authorization->denialMessage($class));

        $action->withActionBatchId($this->record($request, $action, $permitted, $class, $fields));
        $run = $this->runChunk($action, $fields, $permitted);
        app(WidgetCache::class)->flushTables([$permitted->firstOrFail()->getTable()]);
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
     * action sets one; otherwise the Resource ability runAction, and runDestructiveAction as well for
     * a destructive action, asked through the Gate with the action as its argument.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  SupportCollection<int, Model>  $models
     * @return SupportCollection<int, Model>
     */
    public function filterForExecution(LatheRequest $request, Action $action, string $class, SupportCollection $models): SupportCollection
    {
        $user = $request->user();

        // A destructive action needs runDestructiveAction as well as runAction.
        return $models->filter(fn (Model $model) => $action->authorizedToRunOn($request, $model)
            ?? ($this->authorization->allows($user, $class, 'runAction', $model, [$action])
                && (! $action->isDestructive() || $this->authorization->allows($user, $class, 'runDestructiveAction', $model, [$action]))))->values();
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
     * One chunk of a queued run: the handler once for all its models, in one transaction. A
     * throw rolls the chunk back, calls the failed handler for each model, marks their events
     * failed and reaches the job, so the action's tries apply.
     *
     * @param  Collection<int, Model>  $models  one model class, at least one model
     * @param  string|null  $batchId  the run's event batch, or null when it records none
     */
    public function runQueuedChunk(Action $action, ActionFields $fields, Collection $models, ?string $batchId): void
    {
        $first = $models->firstOrFail();

        if ($batchId !== null) {
            $this->chunkEvents($batchId, $models)->where('status', ActionEventStatus::Waiting->value)
                ->update(['status' => ActionEventStatus::Running->value, 'updated_at' => now()]);
        }

        $handler = ActionMethodResolver::handlerFor($action, $first::class);

        try {
            $first->getConnection()->transaction(fn () => $action->{$handler}($fields, $models));
        } catch (Throwable $e) {
            $failedHandler = ActionMethodResolver::failedHandlerFor($action, $first::class);

            foreach ($models as $model) {
                if ($failedHandler !== null) {
                    $action->{$failedHandler}($e, $model);
                }

                $action->markAsFailed($model, $e);
            }

            throw $e;
        }

        if ($batchId !== null) {
            $this->chunkEvents($batchId, $models)->where('status', ActionEventStatus::Running->value)
                ->update(['status' => ActionEventStatus::Finished->value, 'updated_at' => now()]);
        }

        app(WidgetCache::class)->flushTables([$first->getTable()]);
        event(new ActionExecuted($action, $models));
    }

    /**
     * Whether the run goes to the queue instead of running inline: always for a ShouldQueue
     * action, otherwise when the selection holds more rows than the Resource runs inline.
     */
    protected function shouldQueue(Action $action, SelectionResolver $resolver, int $max): bool
    {
        return $action instanceof ShouldQueue || $resolver->exceeds($max);
    }

    /**
     * Starts a queued run: a waiting bulk run row and a batch holding the planner, which
     * reads the selection again in the worker. Authority runs there, row by row.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{result: ActionResult, permitted: int, skipped: int, failed: int, errors: list<array{id: int|string|null, message: string}>, bulk_run: array{id: string, total: int}}
     */
    protected function queue(LatheRequest $request, Action $action, string $class, Selection $selection, SelectionResolver $resolver, ActionFields $fields): array
    {
        $total = $resolver->query()->count();
        $run = BulkRun::begin($request, $class, $action->name(), $total);

        /** @var array<string, mixed> $values */
        $values = $fields->toArray();
        $batch = Bus::batch([new PlanBulkRun($run->id, JobContext::capture($request), $class, $selection->toArray(), $action->uriKey(), $values)]);

        if ($action->connection !== null) {
            $batch->onConnection($action->connection);
        }

        if ($action->queue !== null) {
            $batch->onQueue($action->queue);
        }

        if ($action instanceof BatchableAction) {
            $action->withBatch($fields, $batch);
        }

        $run->dispatchBatch($batch);

        return [
            'result' => ActionResult::message(Lathe::__('This action is being processed...')),
            'permitted' => $total,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'bulk_run' => ['id' => $run->id, 'total' => $total],
        ];
    }

    /**
     * The open events of the chunk's models in the run's batch.
     *
     * @param  Collection<int, Model>  $models
     * @return Builder<ActionEvent>
     */
    protected function chunkEvents(string $batchId, Collection $models): Builder
    {
        return Lathe::actionEventModel()::forBatch($batchId)
            ->where('actionable_type', $models->firstOrFail()->getMorphClass())
            ->whereIn('actionable_id', $models->map(fn (Model $model) => ActionEvent::keyOf($model))->all());
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

        app(WidgetCache::class)->flushTables([$class::newModel()->getTable()]);
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
