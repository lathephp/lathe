<?php

namespace Lathe\Actions\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection as SupportCollection;
use Lathe\ActionEvents\ActionEventRecorder;
use Lathe\Actions\Action;
use Lathe\Actions\ActionDispatcher;
use Lathe\Actions\BulkRun;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Enums\ActionEventStatus;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\Selection;
use Lathe\Query\SelectionResolver;
use Lathe\Resources\Resource;
use Lathe\Support\JobContext;
use RuntimeException;

/**
 * The first job of a bulk run's batch. It reads the selection again, 200 rows at a time,
 * and adds one chunk job per chunk: ExecuteAction for an action, after the rows' authority
 * and their waiting events, or DeleteChunk for a delete, force delete or restore.
 */
class PlanBulkRun implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable;

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     * @param  array<string, mixed>  $selection  Selection::toArray()
     * @param  string|null  $action  the action's uriKey, null for a delete
     * @param  array<string, mixed>  $fields  the validated action fields
     * @param  'delete'|'forceDelete'|'restore'|null  $ability  the delete family write, null for an action
     */
    public function __construct(
        public string $bulkRunId,
        public JobContext $context,
        public string $resource,
        public array $selection,
        public ?string $action = null,
        public array $fields = [],
        public ?string $ability = null,
    ) {
        //
    }

    public function handle(ActionDispatcher $dispatcher, ActionEventRecorder $recorder, ResourceAuthorization $authorization): void
    {
        $batch = $this->batch();

        if ($batch === null || $batch->cancelled()) {
            return;
        }

        $request = self::requestFor($this->context, $this->resource);

        try {
            BulkRun::whereKey($this->bulkRunId)->where('status', 'waiting')->update(['status' => 'running']);

            $seen = $this->ability === null
                ? $this->planAction($batch, $request, $dispatcher, $recorder)
                : $this->planDelete($batch, $request, $authorization, $this->ability);

            // Rows that left the selection since the request counted it.
            $run = BulkRun::find($this->bulkRunId);

            if ($run !== null && $run->total > $seen) {
                BulkRun::addCounts($this->bulkRunId, 0, 0, $run->total - $seen);
            }
        } finally {
            app()->forgetInstance(LatheRequest::class);
        }
    }

    /**
     * Restores the context of the request that started the run and binds a fresh panel
     * request for the Resource, so the code a job runs never reads the original request.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     */
    public static function requestFor(JobContext $context, string $resource): LatheRequest
    {
        $context->restore();

        $request = LatheRequest::create('/'.$resource::uriKey());
        $route = (new Route('GET', '{resource}', []))->bind($request);
        $request->setRouteResolver(fn () => $route);
        app()->instance(LatheRequest::class, $request);

        return $request;
    }

    /**
     * The action the Resource declares under the uriKey, built again in the worker.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     */
    public static function actionFor(LatheRequest $request, string $resource, string $uriKey): Action
    {
        return $resource::make()->availableActions($request)->findByUriKey($uriKey)
            ?? throw new RuntimeException("The action [{$uriKey}] is no longer declared on [{$resource}].");
    }

    /**
     * The selection the job carries, narrowed to the given keys.
     *
     * @param  array<string, mixed>  $selection
     * @param  list<int|string>  $keys
     */
    public static function chunkSelection(array $selection, array $keys): Selection
    {
        return Selection::fromArray(['mode' => 'ids', 'ids' => $keys, 'exclusions' => []] + $selection);
    }

    /**
     * @return int the rows read
     */
    protected function planAction(Batch $batch, LatheRequest $request, ActionDispatcher $dispatcher, ActionEventRecorder $recorder): int
    {
        $action = self::actionFor($request, $this->resource, (string) $this->action);
        $resolver = new SelectionResolver($this->resource::make(), $request, Selection::fromArray($this->selection));
        $seen = 0;

        foreach ($resolver->lazy(200)->chunk($action::$chunkCount) as $chunk) {
            $models = $chunk->values()->collect();
            $allowed = $dispatcher->filterForExecution($request, $action, $this->resource, $models);
            $seen += $models->count();
            BulkRun::addCounts($this->bulkRunId, 0, 0, $models->count() - $allowed->count());

            if ($allowed->isEmpty()) {
                continue;
            }

            if ($action->recordsActionEvents()) {
                $eventBatch = $recorder->forActionRan($request, $action, $allowed, $this->resource, ActionEventStatus::Waiting, $this->fields);
                // The run's events share the job batch id, which the cancel endpoint reads.
                Lathe::actionEventModel()::forBatch($eventBatch)->update(['batch_id' => $batch->id]);
            }

            $job = new ExecuteAction($this->bulkRunId, $this->context, $this->resource, $this->selection, $this->keys($allowed), (string) $this->action, $this->fields);
            $job->timeout = $action->timeout;
            $job->tries = $action->tries;
            $batch = $batch->add([$job->onConnection($action->connection)->onQueue($action->queue)]) ?? $batch;

            if ($batch->cancelled()) {
                break;
            }
        }

        return $seen;
    }

    /**
     * @param  'delete'|'forceDelete'|'restore'  $ability
     * @return int the rows read
     */
    protected function planDelete(Batch $batch, LatheRequest $request, ResourceAuthorization $authorization, string $ability): int
    {
        $resolver = new SelectionResolver($this->resource::make(), $request, Selection::fromArray($this->selection));
        $narrows = $authorization->narrowsBulk($this->resource, $ability);
        $rows = $resolver->lazy(200);

        if ($narrows) {
            $narrowed = $authorization->narrowBulk($request->user(), $this->resource, $ability, $resolver->query());
            $model = $narrowed->getModel();
            $rows = $narrowed->lazyById(200, $model->getQualifiedKeyName(), $model->getKeyName());
        }

        $seen = 0;

        foreach ($rows->chunk(200) as $chunk) {
            $seen += $chunk->count();
            $batch = $batch->add([new DeleteChunk($this->bulkRunId, $this->context, $this->resource, $this->selection, $this->keys($chunk->values()->collect()), $ability, $narrows)]) ?? $batch;

            if ($batch->cancelled()) {
                break;
            }
        }

        return $seen;
    }

    /**
     * @param  SupportCollection<int, Model>  $models
     * @return list<int|string>
     */
    protected function keys(SupportCollection $models): array
    {
        return array_values($models->map(function (Model $model): int|string {
            $key = $model->getKey();

            return is_int($key) || is_string($key) ? $key : (string) json_encode($key);
        })->all());
    }
}
